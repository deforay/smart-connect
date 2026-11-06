#!/bin/bash
#
# Install Smart Connect on a new Ubuntu server.
#
# Download the script to a file, then run it:
#
#   cd ~ && wget -O setup.sh "https://raw.githubusercontent.com/deforay/smart-connect/master/bin/setup.sh?v=$(date +%s)" && sudo bash setup.sh
#
# Do not pipe it into bash. The prompts would read the script's own text.
#
# The script:
#   1. installs Apache, MySQL and PHP with deforay/utility-scripts' lamp-setup.sh,
#      and brings PHP up to its security release (InteLIS's shared-functions.sh);
#   2. clones Smart Connect into the installation folder;
#   3. creates the database and a MySQL user of its own, and imports
#      sys/schema/init.sql or the dump given with --database;
#   4. writes config/autoload/local.php with that user's credentials;
#   5. runs bin/upgrade.sh, which installs the dependencies, runs the migrations,
#      generates the enrollment key, sets permissions and adds the scheduler to
#      the web user's crontab;
#   6. creates the first admin user and the Apache site;
#   7. offers to set up off-machine backups.
#
# It refuses to touch an existing installation or a database that already has
# tables. Use bin/upgrade.sh for an existing installation.
#
# Options:
#   --path=<dir>          installation folder (default /var/www/smart-connect)
#   --server-name=<name>  hostname Apache serves it under (default smart-connect)
#   --db-name=<name>      database name (default vldashboard)
#   --database=<file>     import this dump (.sql, .sql.gz or .sql.zst) instead of
#                         the bundled empty schema, such as a backup from another
#                         server. bin/migrate brings it up to date afterwards.
#   --php=<major.minor>   PHP branch to install (default: 8.4, or 8.5 on Ubuntu 26.04+)
#
# Examples:
#   sudo bash setup.sh
#   sudo bash setup.sh --server-name=dashboard.example.org
#   sudo bash setup.sh --database=/root/vldashboard.sql.gz

set -o pipefail

if [ ! -f "${BASH_SOURCE[0]:-/dev/null}" ]; then
    echo "Do not pipe this script into bash. The prompts would read the script itself."
    echo "Download it first: wget -O setup.sh <url> && sudo bash setup.sh"
    exit 1
fi

if [ "$EUID" -ne 0 ]; then
    echo "Need admin privileges for this script. Run it with sudo."
    exit 1
fi

REPO_GIT_URL="https://github.com/deforay/smart-connect.git"
SHARED_FN_URL="https://raw.githubusercontent.com/deforay/intelis/master/scripts/shared-functions.sh"
LAMP_SETUP_URL="https://raw.githubusercontent.com/deforay/utility-scripts/master/lamp/lamp-setup.sh"
SHARED_FN_PATH="/usr/local/lib/smart-connect/shared-functions.sh"

app_path="/var/www/smart-connect"
server_name="smart-connect"
db_name="vldashboard"
db_dump=""
php_version=""

for arg in "$@"; do
    case "$arg" in
        --path=*) app_path="${arg#*=}" ;;
        --server-name=*) server_name="${arg#*=}" ;;
        --db-name=*) db_name="${arg#*=}" ;;
        --database=*) db_dump="${arg#*=}" ;;
        --php=*) php_version="${arg#*=}" ;;
        *) echo "Unknown option: ${arg}"; exit 2 ;;
    esac
done

app_path="${app_path%/}"
# One MySQL user per database, so a second installation never changes the
# password the first one uses. MySQL user names hold at most 32 characters.
db_user="sc_${db_name}"
db_user="${db_user:0:32}"
[[ "$db_name" =~ ^[A-Za-z0-9_]+$ ]] || { echo "Invalid --db-name: ${db_name}"; exit 2; }
[[ -z "$php_version" || "$php_version" =~ ^[0-9]+\.[0-9]+$ ]] || { echo "Invalid --php: ${php_version}. Expected a branch such as 8.4."; exit 2; }
if [ -n "$db_dump" ]; then
    db_dump="$(cd "$(dirname "$db_dump")" 2>/dev/null && pwd)/$(basename "$db_dump")"
    [ -f "$db_dump" ] || { echo "Dump not found: ${db_dump}"; exit 2; }
fi

if [ -e "$app_path/public/index.php" ]; then
    echo "${app_path} already holds an installation. Run bin/upgrade.sh to update it."
    exit 1
fi

# --------------------------------------------------- shared functions ----

# InteLIS's shared-functions.sh provides prepare_system, the PHP security
# floor, php.ini tuning, Composer and the prompts. Check that the download is
# the real file before sourcing it as root: a failed transfer leaves an empty
# file that sources cleanly and defines nothing.
# A minimal server may have wget, which the install command uses, but no curl.
download_to() {
    if command -v curl >/dev/null 2>&1; then
        curl -fsSL -o "$1" "$2"
    else
        wget -q -O "$1" "$2"
    fi
}

mkdir -p "$(dirname "$SHARED_FN_PATH")"
tmp="$(mktemp "${SHARED_FN_PATH}.XXXXXX")"
if download_to "$tmp" "$SHARED_FN_URL" && grep -q '^prepare_system()' "$tmp"; then
    mv -f "$tmp" "$SHARED_FN_PATH"
else
    rm -f "$tmp"
    [ -f "$SHARED_FN_PATH" ] || { echo "Could not download ${SHARED_FN_URL}. Check the internet connection."; exit 1; }
    echo "Could not download shared-functions.sh. Using the copy already on this machine."
fi
# shellcheck source=/dev/null
source "$SHARED_FN_PATH"
declare -F prepare_system >/dev/null || { echo "${SHARED_FN_PATH} is unusable. Delete it and run this again."; exit 1; }

log_file="/tmp/smart-connect-setup-$(date +'%Y%m%d-%H%M%S').log"
: >"$log_file"

prepare_system
[ -n "$php_version" ] || php_version="$(default_php_version_for_os)"

# ------------------------------------------------------------- inputs ----

print header "Smart Connect setup"
print info "Installation folder: ${app_path}"
print info "Web address: ${server_name}"
print info "Database: ${db_name}"
[ -n "$db_dump" ] && print info "Importing: ${db_dump}"

if [ -f ~/.my.cnf ] && grep -q '^password' ~/.my.cnf; then
    mysql_root_password="$(awk -F= '/^password/ {print $2}' ~/.my.cnf | xargs)"
    print info "Using the MySQL root password in ~/.my.cnf."
else
    while :; do
        read -rsp "MySQL root password (set now if MySQL is not installed yet): " mysql_root_password; echo
        read -rsp "Confirm the password: " confirm; echo
        [ -n "$mysql_root_password" ] && [ "$mysql_root_password" = "$confirm" ] && break
        print warning "The passwords were empty or did not match. Try again."
    done
fi

read -rp "Admin email: " admin_email
read -rp "Admin username: " admin_username
while :; do
    read -rsp "Admin password: " admin_password; echo
    read -rsp "Confirm the admin password: " confirm; echo
    [ -n "$admin_password" ] && [ "$admin_password" = "$confirm" ] && break
    print warning "The passwords were empty or did not match. Try again."
done

# ------------------------------------------------- web server and PHP ----

print header "Installing Apache, MySQL and PHP ${php_version}"
lamp="$(mktemp)"
download_to "$lamp" "$LAMP_SETUP_URL" || { print error "Could not download lamp-setup.sh."; exit 1; }
if ! MYSQL_ROOT_PASSWORD="$mysql_root_password" bash "$lamp" "$php_version"; then
    rm -f "$lamp"
    print error "lamp-setup.sh failed. The messages above show which step. Fix it and run this script again."
    exit 1
fi
rm -f "$lamp"

if ! ensure_php_security_floor; then
    print error "PHP is older than its security release and could not be updated. Update it, then run this script again."
    exit 1
fi
configure_php_ini "$php_version"
ensure_php_cli_extensions "$php_version" || { print error "PHP is missing extensions Composer needs."; exit 1; }
ensure_composer || { print error "Could not install Composer."; exit 1; }
command -v git >/dev/null || apt-get install -y git >>"$log_file" 2>&1

if ! mysqladmin ping -u root -p"$mysql_root_password" &>/dev/null; then
    print error "Cannot log in to MySQL as root with that password."
    exit 1
fi
cat >~/.my.cnf <<EOF
[client]
user=root
password=${mysql_root_password}
EOF
chmod 600 ~/.my.cnf

# ---------------------------------------------------------------- code ----

print header "Downloading Smart Connect"
mkdir -p "$(dirname "$app_path")"
if [ -d "$app_path" ] && [ -n "$(ls -A "$app_path")" ]; then
    print error "${app_path} exists and is not empty. Choose another folder with --path."
    exit 1
fi
git clone --depth 1 --single-branch --branch master "$REPO_GIT_URL" "$app_path" >>"$log_file" 2>&1 ||
    { print error "git clone failed. See ${log_file}."; exit 1; }
rm -rf "$app_path/.git"

# ------------------------------------------------------------ database ----

print header "Creating the database"
tables="$(mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${db_name}'")"
if [ "${tables:-0}" -gt 0 ]; then
    print error "The database ${db_name} already has ${tables} tables. Choose another name with --db-name, or remove it first."
    exit 1
fi

if [ "$(mysql -N -e "SELECT COUNT(*) FROM mysql.user WHERE user = '${db_user}'")" != "0" ]; then
    print error "The MySQL user ${db_user} already exists. Another installation may use it. Choose another --db-name, or drop the user if nothing uses it."
    exit 1
fi

db_password="$(openssl rand -base64 33 | tr -d '/+=' | cut -c1-32)"
mysql <<SQL || { print error "Could not create the database or its user."; exit 1; }
CREATE DATABASE IF NOT EXISTS \`${db_name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER '${db_user}'@'localhost' IDENTIFIED BY '${db_password}';
GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${db_user}'@'localhost';
SQL

if [ -n "$db_dump" ]; then
    print info "Importing ${db_dump}..."
    case "$db_dump" in
        *.sql.gz) gunzip -c "$db_dump" | mysql "$db_name" ;;
        *.sql.zst) zstd -dc "$db_dump" | mysql "$db_name" ;;
        *) mysql "$db_name" <"$db_dump" ;;
    esac
else
    print info "Importing the bundled schema..."
    mysql "$db_name" <"$app_path/sys/schema/init.sql"
fi
[ $? -eq 0 ] || { print error "The import failed. Drop the database ${db_name} and run this script again."; exit 1; }
print success "Database ${db_name} ready."

# --------------------------------------------------------- configuration --

cat >"$app_path/config/autoload/local.php" <<EOF
<?php

// Written by bin/setup.sh. The database name and host are in global.php.
return [
    'db' => [
        'username' => '${db_user}',
        'password' => '${db_password}',
    ],
];
EOF
chmod 640 "$app_path/config/autoload/local.php"
if [ "$db_name" != "vldashboard" ]; then
    sed -i "s/dbname=vldashboard;/dbname=${db_name};/" "$app_path/config/autoload/global.php"
fi

# ----------------------------------------- dependencies and migrations ----

# bin/upgrade.sh is the one deployment path. On a new tree it installs the
# Composer dependencies, seeds the remaining config files, generates the
# enrollment key, runs the migrations, sets permissions and adds cron.sh to the
# web user's crontab.
print header "Installing dependencies and running migrations"
if ! bash "$app_path/bin/upgrade.sh" -p "$app_path" -y -b; then
    print error "bin/upgrade.sh failed. Run it again once the problem above is fixed:"
    print info "  sudo bash ${app_path}/bin/upgrade.sh -p ${app_path}"
    exit 1
fi

web_user="www-data"
print info "Creating the admin user..."
sudo -u "$web_user" php "$app_path/bin/console" seed-admin \
    --email="$admin_email" --username="$admin_username" --password="$admin_password" ||
    print warning "Could not create the admin user. Run: sudo -u ${web_user} php ${app_path}/bin/console seed-admin"

# ------------------------------------------------------------ web site ----

print header "Configuring Apache"
a2enmod rewrite >>"$log_file" 2>&1
site="/etc/apache2/sites-available/smart-connect.conf"
[ "$app_path" = "/var/www/smart-connect" ] || site="/etc/apache2/sites-available/$(basename "$app_path").conf"
cat >"$site" <<EOF
<VirtualHost *:80>
    ServerName ${server_name}
    DocumentRoot "${app_path}/public"

    <Directory "${app_path}/public">
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/$(basename "$app_path")-error.log
    CustomLog \${APACHE_LOG_DIR}/$(basename "$app_path")-access.log combined
</VirtualHost>
EOF
a2ensite "$(basename "$site")" >>"$log_file" 2>&1
hosts_file_add_local "$server_name" || true
if apache2ctl configtest >>"$log_file" 2>&1; then
    restart_service apache || print warning "Could not restart Apache. Run: systemctl restart apache2"
else
    print warning "The Apache configuration does not pass apache2ctl configtest. See ${log_file}."
fi

# ------------------------------------------------------------- backups ----

if ask_yes_no "Set up off-machine backups now" "yes"; then
    bash "$app_path/bin/remote-backup.sh" ||
        print warning "Backup setup did not finish. Run it later: sudo bash ${app_path}/bin/remote-backup.sh"
else
    print info "Set up backups later: sudo bash ${app_path}/bin/remote-backup.sh"
fi

print header "Smart Connect is installed"
print info "Open ${server_name} in a browser and log in as ${admin_email}."
print info "The enrollment key, which each LIS needs: sudo -u ${web_user} php ${app_path}/bin/generate-enrollment-key.php --show"
print info "Log: ${log_file}"

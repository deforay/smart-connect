#!/bin/bash

# Upgrade smart-connect deployments in place.
#
# Run it straight from the repo:
#
#   sudo bash -c "$(curl -fsSL "https://raw.githubusercontent.com/deforay/smart-connect/master/bin/upgrade.sh?v=$(date +%s)")"
#
# Note the shape. The script is an argument, not stdin. Piping it in as
# `curl ... | sudo bash` breaks the prompts. Where sudoers sets use_pty, which
# is the default on Ubuntu 22.04 and sudo 1.9.14+, sudo runs the script on a pty
# it owns and feeds that pty from its own stdin. That stdin is the curl pipe, so
# the prompt reaches you but your answer never reaches the script.
#
# Every instance is deployed the same way. A shallow source mirror is refreshed
# once per run and rsynced over the tree, with .git excluded. An instance's own
# git state is never consulted. In the field a .git is no proof of a maintained
# checkout, and a HEAD that trails the files on disk is enough to block an
# upgrade that a plain copy would have completed.
#
# Four files under config/autoload belong to the deployment and are never
# overwritten: global.php holds the DSN, local.php holds the credentials, and
# custom.global.php holds the enrollment key and SMTP settings. Any *.local.php
# is treated the same way. Everything else under config/autoload is upstream
# code and gets updated.
#
# It deliberately does no system-level work. PHP, MySQL and Apache belong to the
# setup script.
#
# Databases are dumped with the app's own db-tools where vendor/ has it, which
# means a .sql.zst archive restorable with:
#
#   (cd /var/www/smart-connect && php vendor/bin/db-tools restore /var/smart-connect-backup/db/NAME.sql.zst)
#
# Trees too old to carry db-tools fall back to mysqldump and a .sql.gz.
#
# Usage:
#   sudo bin/upgrade.sh [-p PATH] [-b] [-y]
#
# With no -p it upgrades every smart-connect instance found in /var/www. Almost
# every server hosts exactly one, so that is the whole command. Servers that
# also run a training instance get both in one pass.
#
# Options:
#   -p PATH  upgrade only this instance, wherever it lives
#   -b       skip the backups. The config/autoload tarball is always taken
#   -y       non-interactive. Every prompt takes its default, which is the safe
#            answer, so a failed backup stops that instance rather than plough on
#
# Env:
#   SMART_CONNECT_SRC_DIR   source mirror location
#                           (default /usr/local/lib/smart-connect/src)

set -o pipefail

if [ "$EUID" -ne 0 ]; then
    echo "Need admin privileges for this script. Run it with sudo."
    exit 1
fi

log_file="/tmp/smart-connect-upgrade-$(date +'%Y%m%d-%H%M%S').log"
stamp="$(date +'%Y%m%d-%H%M%S')"

REPO_GIT_URL="https://github.com/deforay/smart-connect.git"
REPO_TARBALL_URL="https://codeload.github.com/deforay/smart-connect/tar.gz/refs/heads/master"
REPO_SCRIPT_URL="https://raw.githubusercontent.com/deforay/smart-connect/master/bin/upgrade.sh"
SRC_DIR="${SMART_CONNECT_SRC_DIR:-/usr/local/lib/smart-connect/src}"
SEARCH_DIR="/var/www"
BACKUP_ROOT="/var/smart-connect-backup"

# Files under config/autoload that carry this deployment's own settings. rsync
# never transfers them, and each one is seeded from its .dist template when an
# instance turns out not to have it.
DEPLOYMENT_CONFIG=(
    'config/autoload/global.php'
    'config/autoload/local.php'
    'config/autoload/custom.global.php'
    'config/autoload/*.local.php'
)

# ---------------------------------------------------------------- helpers ---

print() {
    case "$1" in
        error)   printf "\033[1;91m❌ Error:\033[0m %s\n" "$2" ;;
        success) printf "\033[1;92m✅ Success:\033[0m %s\n" "$2" ;;
        warning) printf "\033[1;93m⚠️  Warning:\033[0m %s\n" "$2" ;;
        info)    printf "\033[1;96mℹ️  Info:\033[0m %s\n" "$2" ;;
        header)  printf "\n\033[1;96m===== %s =====\033[0m\n\n" "$2" ;;
        *)       printf "%s\n" "$2" ;;
    esac
}

log_action() {
    echo "$(date +'%Y-%m-%d %H:%M:%S') - $1" >>"$log_file"
}

say() {
    print "$1" "$2"
    log_action "$2"
}

# Fatal for the whole run.
die() {
    say error "$1"
    exit 1
}

# Fatal for the current instance only, so one bad instance does not stop the rest.
fail() {
    say error "$1"
    return 1
}

# A terminal that takes our prompt but never returns an answer. See ask_yes_no.
prompt_is_deaf=false

# Prompts read from the terminal, not stdin, because the script itself arrives
# on stdin when it is piped in from curl. Without a terminal at all, under cron
# or CI, the default wins.
ask_yes_no() {
    local question="$1" default="${2:-no}" answer

    # Probe by opening the terminal rather than testing for it. /dev/tty exists
    # under cron and in containers but cannot be opened, and the failed redirect
    # prints noise of its own.
    if [ "$assume_defaults" = true ] || [ "$prompt_is_deaf" = true ]; then
        print info "${question}? [auto: ${default}]"
        [ "$default" = "yes" ]
        return
    fi

    # Real stdin first. It is the terminal for every invocation except the curl
    # pipe, and unlike /dev/tty it cannot be some other process's pty.
    if [ -t 0 ]; then
        exec 3<&0
    elif ! { exec 3<>/dev/tty; } 2>/dev/null; then
        print info "${question}? [auto: ${default}]"
        [ "$default" = "yes" ]
        return
    fi

    # Prompt on the terminal rather than stdout. The script arrives on stdin from
    # curl, so stdout and the terminal are not the same thing here. A prompt
    # written to one while the answer is read from the other looks like a script
    # that ignores what you type.
    printf "%s? [default: %s, auto in 60s] " "$question" "$default" >&3
    if ! read -r -t 60 answer <&3; then
        printf '\n' >&3
        # /dev/tty can be writable and still deliver nothing. Under
        # `curl | sudo bash` with sudoers' use_pty, the default on Ubuntu 22.04
        # and sudo 1.9.14+, the script runs on a pty sudo owns and sudo feeds
        # that pty from its own stdin, which is the curl pipe. The prompt
        # appears, the terminal echoes what you type, and the answer goes to the
        # shell that launched curl. It does not start working on the next
        # question, so stop spending a minute apiece to ask it.
        prompt_is_deaf=true
        say warning "No answer reached this script in 60s. Taking the default for this and every later question."
        say warning "If you did type one, the pipe swallowed it. Run it as: sudo bash -c \"\$(curl -fsSL ${REPO_SCRIPT_URL})\""
        answer="$default"
    fi
    exec 3>&-

    answer="$(printf '%s' "${answer:-$default}" | tr '[:upper:]' '[:lower:]')"
    [ "$answer" = "y" ] || [ "$answer" = "yes" ]
}

as_web() {
    sudo -u "$web_user" "$@"
}

# safe.directory keeps git from refusing a checkout it does not own. The
# low-speed abort stops a dead link from hanging the whole upgrade.
run_git() {
    git -c safe.directory='*' -c http.lowSpeedLimit=1000 -c http.lowSpeedTime=60 "$@"
}

# A smart-connect tree, as opposed to any other Laminas app sharing the server.
# The composer.json name is the discriminating marker. bin/migrate is
# deliberately NOT required, because installs old enough to lack it are
# precisely the ones that need upgrading. The deploy puts it there.
is_smart_connect_path() {
    [ -f "$1/composer.json" ] &&
    grep -q '"deforay/smart-connect"' "$1/composer.json" 2>/dev/null &&
    [ -f "$1/public/index.php" ]
}

# Which part of the check failed, so a rejected path says why rather than
# leaving the operator to guess at three conditions.
why_not_smart_connect() {
    if [ ! -f "$1/composer.json" ]; then
        echo "no composer.json there"
    elif ! grep -q '"deforay/smart-connect"' "$1/composer.json" 2>/dev/null; then
        echo "composer.json is not deforay/smart-connect"
    elif [ ! -f "$1/public/index.php" ]; then
        echo "no public/index.php"
    else
        echo "unknown reason"
    fi
}

checksum() {
    [ -f "$1" ] && md5sum "$1" | awk '{print $1}' || echo "none"
}

# Whichever downloader the box has. The tarball fallback is for machines without
# git, which are also the ones most likely to be missing one of these.
fetch_url() {
    local url="$1" dest="$2"
    if command -v curl &>/dev/null; then
        curl -fsSL --retry 3 -o "$dest" "$url" 2>>"$log_file"
    elif command -v wget &>/dev/null; then
        wget -q -O "$dest" "$url" 2>>"$log_file"
    else
        return 1
    fi
}

# The merged config decides which database an instance talks to, and with which
# credentials. Mirror the glob in config/application.config.php and the app's
# merge order rather than guessing from a single file. global.php holds the DSN
# and local.php holds the credentials, so neither file alone is enough. Emits
# name, user, password, host and port, one per line, so a value containing
# spaces survives the round trip.
resolve_db_config() {
    (cd "$app_path" && as_web php -r '
        $files = glob("config/autoload/{{,*.}global,{,*.}local}.php", GLOB_BRACE) ?: [];
        $db = [];
        foreach ($files as $file) {
            $conf = include $file;
            if (is_array($conf) && isset($conf["db"]) && is_array($conf["db"])) {
                $db = array_merge($db, $conf["db"]);
            }
        }
        $name = $host = $port = "";
        if (!empty($db["dsn"])) {
            foreach (["dbname" => "name", "host" => "host", "port" => "port"] as $key => $var) {
                if (preg_match("/" . $key . "=([^;]+)/", $db["dsn"], $m)) {
                    $$var = trim($m[1]);
                }
            }
        }
        if ($name === "" && !empty($db["data-base-name"])) $name = trim($db["data-base-name"]);
        if ($host === "" && !empty($db["data-base-host"])) $host = trim($db["data-base-host"]);
        if ($port === "" && !empty($db["port"])) $port = trim($db["port"]);
        foreach ([$name, $db["username"] ?? "", $db["password"] ?? "", $host, $port] as $value) {
            echo str_replace(["\r", "\n"], "", (string) $value), "\n";
        }
    ' 2>/dev/null)
}

# mysqldump needs a password on most boxes. Putting it on the command line would
# expose it in ps, so it goes in a 0600 option file instead. Option-file values
# are quoted because a password may contain # or ; which otherwise start a
# comment.
write_defaults_file() {
    local user="$1" pass="$2" host="$3" port="$4" escaped

    defaults_file="$(mktemp)"
    chmod 600 "$defaults_file"
    {
        printf '[client]\n'
        escaped="${user//\\/\\\\}"; printf 'user="%s"\n' "${escaped//\"/\\\"}"
        if [ -n "$pass" ]; then
            escaped="${pass//\\/\\\\}"; printf 'password="%s"\n' "${escaped//\"/\\\"}"
        fi
        [ -n "$host" ] && printf 'host="%s"\n' "$host"
        [ -n "$port" ] && printf 'port=%s\n' "$port"
    } >"$defaults_file"
}

# db-tools is the app's own backup tool. db-tools.php reads the credentials out
# of config/autoload, and the archive comes out zstd-compressed where zstd is
# installed, or pigz or gzip otherwise. It writes its own filename into the
# output directory, so the path is read back from what it prints.
#
# Encryption is on by default and retention would prune older archives. An
# upgrade backup has to be restorable without a key and must never delete a
# previous one, hence --no-encrypt and --retention=0. The output directory is
# outside the app tree, so Housekeeping never prunes it either.
db_tools_dump() {
    local output status path

    [ -f "$app_path/vendor/bin/db-tools" ] && [ -f "$app_path/db-tools.php" ] || return 1

    # As root, unlike the rest of the run. $BACKUP_ROOT is root-owned, and the
    # ownership sweep later in the upgrade tidies anything left in the app tree.
    output="$(cd "$app_path" && php vendor/bin/db-tools backup \
        --output-dir="$BACKUP_ROOT/db" --no-encrypt --retention=0 \
        --no-interaction 2>&1)"
    status=$?
    printf '%s\n' "$output" >>"$log_file"
    [ $status -eq 0 ] || return 1

    path="$(printf '%s\n' "$output" | grep -oE '/[^[:space:]]+\.sql\.(zst|gz|zip)' | tail -1)"
    [ -n "$path" ] && [ -f "$path" ] || return 1

    db_backup="$path"
}

# mysqldump, for trees whose vendor/ predates db-tools. The backup runs before
# composer install, so the new source has not landed yet. Root over the unix
# socket needs no credentials, but it only authenticates where root uses
# auth_socket. The app user always reaches its own database, though it may lack
# PROCESS for tablespaces and the rights to read routines and triggers, hence
# the narrowing retries.
mysqldump_fallback() {
    local rc=1
    local -a attempt

    db_backup="${BACKUP_ROOT}/db/${db_name}-${stamp}.sql.gz"

    if mysqldump --opt --routines --triggers --databases "$db_name" 2>>"$log_file" | gzip >"$db_backup"; then
        return 0
    fi

    [ -n "$db_user" ] || { rm -f "$db_backup"; return 1; }
    say info "Root has a password here. Retrying the dump as ${db_user} from config/autoload."
    write_defaults_file "$db_user" "$db_pass" "$db_host" "$db_port"

    attempt=(--routines --triggers --no-tablespaces)
    while :; do
        if mysqldump --defaults-file="$defaults_file" --opt "${attempt[@]}" \
            --databases "$db_name" 2>>"$log_file" | gzip >"$db_backup"; then
            [ ${#attempt[@]} -eq 1 ] &&
                say warning "Dumped without routines or triggers. ${db_user} may not read them."
            rc=0
            break
        fi
        # Drop --routines --triggers and try once more. Those are the privileges
        # an application user is most often denied.
        [ ${#attempt[@]} -gt 1 ] || break
        attempt=(--no-tablespaces)
    done

    # The option file holds the password in clear, so it does not outlive the dump.
    rm -f "$defaults_file"
    defaults_file=""
    [ $rc -eq 0 ] || rm -f "$db_backup"
    return $rc
}

# Sets db_backup to whatever was written.
dump_database() {
    db_tools_dump && return 0
    mysqldump_fallback
}

# What version an instance is on. VERSION.txt is what the deploy stamps, so it
# wins. A tree that happens to carry a .git is not deployed through it, and that
# HEAD describes whatever was last committed there, not what is on disk.
installed_ref() {
    if [ -f "$app_path/VERSION.txt" ]; then
        head -1 "$app_path/VERSION.txt"
    elif [ -e "$app_path/.git" ]; then
        run_git -C "$app_path" rev-parse --short HEAD 2>/dev/null
    else
        echo "unknown"
    fi
}

detect_installations() {
    local dir
    for dir in "$SEARCH_DIR"/*/; do
        dir="${dir%/}"
        [ -d "$dir" ] || continue
        is_smart_connect_path "$dir" && printf '%s\n' "$dir"
    done
}

# Settings the new release added to a .dist template that this deployment's own
# copy does not have yet. The app falls back to its compiled-in defaults for
# them, so nothing breaks, but an operator who needs to set one has to be told
# it exists. Reports dotted key paths, one per line, and stays quiet when the
# two files agree.
report_new_config_keys() {
    local live="$1" dist="$2" label="$3" missing

    [ -f "$live" ] && [ -f "$dist" ] || return 0

    missing="$(cd "$app_path" && as_web php -r '
        function paths(array $a, string $prefix = ""): array {
            $out = [];
            foreach ($a as $key => $value) {
                $path = $prefix === "" ? (string) $key : $prefix . "." . $key;
                // Only descend into associative arrays. A list is a value the
                // operator sets whole, not a set of separately-named settings.
                if (is_array($value) && $value !== [] && array_keys($value) !== range(0, count($value) - 1)) {
                    $out = array_merge($out, paths($value, $path));
                } else {
                    $out[] = $path;
                }
            }
            return $out;
        }
        $live = include $argv[1];
        $dist = include $argv[2];
        if (!is_array($live) || !is_array($dist)) exit(0);
        foreach (array_diff(paths($dist), paths($live)) as $path) {
            echo $path, "\n";
        }
    ' "$live" "$dist" 2>/dev/null)"

    [ -n "$missing" ] || return 0

    say warning "${label} has settings this deployment does not: $(printf '%s' "$missing" | tr '\n' ' ')"
    say warning "The app uses its built-in defaults for those. Add them to ${live} to override."
}

# Copy a .dist template into place when the deployment's own file is absent.
# Fresh installs and instances upgraded by hand both reach this.
seed_from_dist() {
    local target="$1" dist="$2" note="$3"

    [ ! -f "$app_path/$target" ] || return 0
    [ -f "$app_path/$dist" ] || return 0

    cp -a "$app_path/$dist" "$app_path/$target" || return 1
    say warning "${target} was missing. Copied it from ${dist}. ${note}"
}

trap 'rc=$?; [ $rc -ne 0 ] && print info "Full log: '"$log_file"'"; exit $rc' EXIT

# ----------------------------------------------------------------- source ---

# A persistent shallow mirror is updated with delta fetches, so after the first
# clone each run transfers only what changed instead of a fresh tarball. The
# tarball is the last resort for boxes without git. Acquired once per run and
# shared by every instance that needs it.
src_dir=""
temp_dir=""

acquire_source() {
    [ -z "$src_dir" ] || return 0

    print header "Downloading smart-connect"
    temp_dir="$(mktemp -d)"

    if command -v git &>/dev/null && [ -d "$SRC_DIR/.git" ]; then
        print info "Updating the source mirror (delta fetch)..."
        if run_git -C "$SRC_DIR" fetch --depth 1 origin master >>"$log_file" 2>&1 &&
            run_git -C "$SRC_DIR" reset --hard FETCH_HEAD >>"$log_file" 2>&1 &&
            run_git -C "$SRC_DIR" clean -fd >>"$log_file" 2>&1; then
            # A shallow fetch orphans the old tip. Sweep it so the mirror does
            # not bloat across upgrades.
            run_git -C "$SRC_DIR" gc --prune=now --quiet >>"$log_file" 2>&1 || true
            src_dir="$SRC_DIR"
            say success "Source mirror updated."
        else
            say warning "Delta fetch failed. Re-cloning the mirror."
            rm -rf "$SRC_DIR"
        fi
    fi

    if [ -z "$src_dir" ] && command -v git &>/dev/null; then
        print info "Cloning master into the source mirror (shallow)..."
        mkdir -p "$(dirname "$SRC_DIR")"
        rm -rf "$SRC_DIR"
        if run_git clone --depth 1 --single-branch --branch master \
            "$REPO_GIT_URL" "$SRC_DIR" >>"$log_file" 2>&1; then
            src_dir="$SRC_DIR"
            say success "Source cloned. Later runs fetch deltas only."
        else
            say warning "git clone failed. See ${log_file}."
        fi
    fi

    if [ -z "$src_dir" ]; then
        print info "Falling back to the tarball..."
        if fetch_url "$REPO_TARBALL_URL" "$temp_dir/master.tar.gz" &&
            gzip -t "$temp_dir/master.tar.gz" 2>>"$log_file" &&
            tar -xzf "$temp_dir/master.tar.gz" -C "$temp_dir" 2>>"$log_file" &&
            [ -d "$temp_dir/smart-connect-master" ]; then
            src_dir="$temp_dir/smart-connect-master"
            say success "Source obtained via tarball."
        fi
    fi

    [ -n "$src_dir" ] || return 1

    # Stamp the ref into the tree. .git never reaches an instance, so VERSION.txt
    # is the only way an rsynced install knows what it runs.
    if [ -d "$src_dir/.git" ]; then
        run_git -C "$src_dir" rev-parse --short HEAD >"$src_dir/VERSION.txt" 2>/dev/null || true
    else
        echo "tarball-${stamp}" >"$src_dir/VERSION.txt"
    fi
}

# -------------------------------------------------------------- instance ----

upgrade_instance() {
    app_path="$1"
    local position="$2" total="$3"
    local ref_before ref_after lock_before
    local config_backup db_backup exclude pattern
    local -a rsync_excludes
    # Dynamically scoped, so dump_database and write_defaults_file see them.
    local db_name db_user db_pass db_host db_port defaults_file=""

    print header "Upgrading ${position}/${total}: ${app_path}"
    say info "Currently at: $(installed_ref)"

    # --- backups ------------------------------------------------------------
    mkdir -p "$BACKUP_ROOT/db" "$BACKUP_ROOT/config"

    # The deploy rewrites tracked files under config/autoload, so this tarball is
    # taken on every run regardless of -b. It is a few kilobytes.
    config_backup="${BACKUP_ROOT}/config/$(basename "$app_path")-autoload-${stamp}.tar.gz"
    tar -czf "$config_backup" -C "$app_path" config/autoload ||
        { fail "Could not archive config/autoload for ${app_path}."; return 1; }
    say success "Configuration backed up to ${config_backup}"

    { read -r db_name; read -r db_user; read -r db_pass; read -r db_host; read -r db_port; } < <(resolve_db_config)
    [ -n "$db_name" ] || say warning "Could not resolve the database name from config/autoload."

    if [ "$skip_backups" = false ] && [ -n "$db_name" ]; then
        # Two instances can share one database. The archive name is db-tools' to
        # choose, so track what has been dumped rather than testing for a path
        # this run would have picked.
        if printf '%s\n' "${dumped_dbs[@]}" | grep -Fxq "$db_name"; then
            say info "Database ${db_name} already dumped this run."
        else
            print info "Dumping database ${db_name}..."
            if dump_database; then
                dumped_dbs+=("$db_name")
                say success "Database backed up to ${db_backup} ($(du -h "$db_backup" | cut -f1))"
            else
                say warning "Database dump failed. Check ${log_file}."
                ask_yes_no "Continue upgrading ${app_path} without a database backup" "no" ||
                    { fail "Skipped ${app_path}: no database backup."; return 1; }
            fi
        fi
    elif [ "$skip_backups" = true ]; then
        say info "Skipping database backup (-b)."
    fi

    # The code is not backed up. The deploy overwrites the tree, but the code
    # comes back from git, config/autoload is already tarballed above, and the
    # deploy neither deletes untracked files nor touches uploads. Copying the
    # whole folder aside is the slowest step of an upgrade by far and buys none
    # of that back.

    # --- source -------------------------------------------------------------
    lock_before="$(checksum "$app_path/composer.lock")"
    ref_before="$(installed_ref)"

    acquire_source ||
        { fail "Could not obtain the source (mirror, clone and tarball all failed)."; return 1; }

    # Excluding a path is enough to protect it, because the deploy runs without
    # --delete. rsync never writes an excluded file, so the deployment's own copy
    # stays exactly as it was. The rest of config/autoload is upstream code and
    # does get updated, which is why the whole directory is not excluded.
    rsync_excludes=()
    for pattern in "${DEPLOYMENT_CONFIG[@]}"; do
        rsync_excludes+=(--exclude="$pattern")
    done
    for exclude in '.git' '.env' 'vendor/' 'data/cache/' 'data/logs/' \
        'public/uploads/' 'public/temporary/' 'temporary/' 'backup/'; do
        rsync_excludes+=(--exclude="$exclude")
    done

    # Symlinked directories are how instances point uploads at another volume.
    # -K fills them rather than replacing them with real dirs.
    print info "Deploying source into ${app_path}..."
    rsync -a -K --info=progress2 "${rsync_excludes[@]}" \
        "$src_dir/" "$app_path/" 2>>"$log_file" ||
        { fail "rsync deploy failed for ${app_path}. See ${log_file}."; return 1; }

    # sys/migrations holds nothing a deployment owns, so the release decides what
    # is in it. The deploy above runs without --delete, so a migration renamed
    # between releases would otherwise stay on disk beside its replacement, and
    # bin/migrate would then apply the same schema twice.
    rsync -a --delete "$src_dir/sys/migrations/" "$app_path/sys/migrations/" 2>>"$log_file" ||
        { fail "Could not refresh sys/migrations in ${app_path}. See ${log_file}."; return 1; }

    say success "Source deployed. This deployment's config/autoload files were left untouched."

    # An instance can arrive here without one of the deployment-owned files.
    # Fresh trees have none of them, and instances upgraded by hand lost
    # custom.global.php when it became gitignored. Seed each from its template so
    # the app boots, and say what still needs checking.
    seed_from_dist 'config/autoload/local.php' 'config/autoload/local.php.dist' \
        "Set the database username and password in it." ||
        { fail "Could not seed config/autoload/local.php for ${app_path}."; return 1; }
    seed_from_dist 'config/autoload/custom.global.php' 'config/autoload/custom.global.dist.php' \
        "SMTP settings are empty in it." ||
        { fail "Could not seed config/autoload/custom.global.php for ${app_path}."; return 1; }

    # global.php ships with the DSN pointing at a default database name, so a
    # seeded copy is almost certainly wrong. Say so rather than let migrate fail
    # against a database that does not exist.
    if [ ! -f "$app_path/config/autoload/global.php" ] &&
       [ -f "$src_dir/config/autoload/global.php" ]; then
        cp -a "$src_dir/config/autoload/global.php" "$app_path/config/autoload/global.php" ||
            { fail "Could not seed config/autoload/global.php for ${app_path}."; return 1; }
        say warning "config/autoload/global.php was missing. Copied the upstream one. Check the DSN's dbname before trusting it."
    fi

    report_new_config_keys "$app_path/config/autoload/custom.global.php" \
        "$app_path/config/autoload/custom.global.dist.php" "custom.global.dist.php"

    # global.php is upstream code apart from the DSN, so a new release can add
    # keys to it that this deployment's frozen copy never receives. Compare
    # against the mirror rather than a .dist template, because global.php is its
    # own template.
    report_new_config_keys "$app_path/config/autoload/global.php" \
        "$src_dir/config/autoload/global.php" "The new global.php"

    ref_after="$(installed_ref)"
    if [ "$ref_before" = "$ref_after" ]; then
        say info "Already up to date at ${ref_after}."
    else
        say success "Updated ${ref_before} → ${ref_after}"
    fi

    # A leftover .git now describes a commit the files no longer match, so say so
    # once rather than let git status mislead whoever looks next.
    if [ -e "$app_path/.git" ]; then
        say warning "${app_path} still has a .git. It is not used for deployment, and its HEAD is now stale."
    fi

    # Ownership before composer, not after. The deploy can land root-owned files,
    # and composer runs as the web user.
    chown -R "$web_user":"$web_user" "$app_path"

    # --- dependencies -------------------------------------------------------
    cd "$app_path" || { fail "Could not enter ${app_path}."; return 1; }

    if [ ! -d "$app_path/vendor" ] ||
       [ "$ref_before" != "$ref_after" ] ||
       [ "$lock_before" != "$(checksum "$app_path/composer.lock")" ]; then
        print info "Installing dependencies..."
        as_web composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader 2>&1 |
            tee -a "$log_file" || { fail "composer install failed for ${app_path}."; return 1; }
        say success "Dependencies installed."
    else
        as_web composer dump-autoload -o --no-interaction 2>&1 | tee -a "$log_file"
        say info "Source unchanged. Refreshed the autoloader only."
    fi

    # --- caches -------------------------------------------------------------
    # composer's post-install-cmd already clears the merged config cache, but the
    # dump-autoload branch above does not trigger it. Clearing twice costs
    # nothing. data/cache/app is the symfony/cache pool, which nothing else
    # clears, and it holds entries keyed on config the upgrade may have changed.
    as_web php bin/clear-config-cache.php >>"$log_file" 2>&1 || true
    rm -rf "${app_path:?}/data/cache/app"
    say success "Config cache and application cache cleared."

    # --- enrollment key -----------------------------------------------------
    # Every LIS enrolls itself with this key, so an instance without one accepts
    # no new clients. The script is idempotent and leaves an existing key alone.
    print info "Checking the API enrollment key..."
    as_web php bin/generate-enrollment-key.php 2>&1 | tee -a "$log_file" ||
        say warning "Could not generate the enrollment key. Run: (cd ${app_path} && sudo -u ${web_user} php bin/generate-enrollment-key.php)"

    # --- migrations ---------------------------------------------------------
    print info "Running database migrations..."
    as_web php bin/migrate 2>&1 | tee -a "$log_file" ||
        { fail "Migrations failed for ${app_path}. See ${log_file}."; return 1; }

    # A migration run can report progress and still leave the schema behind the
    # code. Confirm the two versions match rather than trusting the run.
    print info "Verifying the schema version..."
    local version_output version_status
    version_output="$(as_web php bin/check-version-sync 2>&1)" && version_status=0 || version_status=$?
    printf '%s\n' "$version_output" | tee -a "$log_file"

    if [ "$version_status" -eq 0 ]; then
        say success "Schema is at the version this code expects."
    else
        say warning "Version check failed for ${app_path}. Re-run: (cd ${app_path} && sudo -u ${web_user} php bin/migrate)"
    fi

    # --- permissions --------------------------------------------------------
    # Directories the app writes to at runtime. Git tracks none of their
    # contents, and the rsync deploy excludes them, so they can be missing.
    local dir
    for dir in data/cache data/cache/app data/logs backup backup/db temporary \
        public/temporary public/temporary/vlsm-vl public/temporary/vlsm-eid \
        public/temporary/vlsm-covid19 public/temporary/vlsm-reference \
        public/uploads public/uploads/not-import-vl \
        public/uploads/track-api/requests public/uploads/track-api/responses; do
        mkdir -p "$app_path/$dir"
    done

    chown -R "$web_user":"$web_user" "$app_path"
    chmod -R u+rwX,g+rwX "$app_path/data" "$app_path/backup" "$app_path/temporary" \
        "$app_path/public/temporary" "$app_path/public/uploads"
    chmod +x "$app_path/cron.sh" "$app_path/bin/migrate" "$app_path/bin/console" \
        "$app_path/bin/check-version-sync" "$app_path/bin/upgrade.sh" 2>/dev/null

    say success "Ownership set to ${web_user} and runtime directories made writable."

    # --- housekeeping -------------------------------------------------------
    # Same command the daily cron runs. Doing it here clears whatever the
    # previous release left behind before the new one starts writing.
    as_web php bin/console housekeeping >>"$log_file" 2>&1 ||
        say warning "Housekeeping did not complete. See ${log_file}."

    # --- cron ---------------------------------------------------------------
    # crunz reads crunz.yml from the working directory. cron.sh cd's there
    # itself, so the crontab line needs no cd of its own.
    local cron_line current_crontab
    cron_line="* * * * * ${app_path}/cron.sh >> ${app_path}/data/logs/crunz.log 2>&1"
    current_crontab="$(crontab -u "$web_user" -l 2>/dev/null || true)"

    if printf '%s\n' "$current_crontab" | grep -Fq "${app_path}/cron.sh"; then
        say info "crunz cron entry already present for ${app_path}."
    else
        {
            if [ -z "$current_crontab" ]; then
                printf 'MAILTO=""\n'
                printf 'PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\n\n'
            else
                printf '%s\n' "$current_crontab"
            fi
            printf '%s\n' "$cron_line"
        } | crontab -u "$web_user" -
        say success "crunz cron entry added to the ${web_user} crontab."
    fi

    say success "${app_path} upgraded."
    return 0
}

# ------------------------------------------------------------------ flags ---

app_path=""
target_path=""
skip_backups=false
assume_defaults=false

while getopts ":p:by" opt; do
    case $opt in
        p) target_path="$OPTARG" ;;
        b) skip_backups=true ;;
        y) assume_defaults=true ;;
        *) : ;;
    esac
done

for cmd in php composer rsync; do
    command -v "$cmd" &>/dev/null || die "${cmd} is not installed."
done

declare -a app_paths=()

# Scanning is the default. A server almost always holds one instance, and
# finding it is exactly what -p would have been typed to say.
if [ -n "$target_path" ]; then
    target_path="$(cd "$target_path" 2>/dev/null && pwd)" || die "Path not found: ${target_path}"
    is_smart_connect_path "$target_path" ||
        die "${target_path} does not look like a smart-connect installation ($(why_not_smart_connect "$target_path"))."
    app_paths=("$target_path")
else
    print info "Scanning ${SEARCH_DIR} for smart-connect installations..."
    mapfile -t app_paths < <(detect_installations)
    [ ${#app_paths[@]} -gt 0 ] ||
        die "No smart-connect installations found in ${SEARCH_DIR}. Name one with -p PATH."
fi

web_user="www-data"
id "$web_user" &>/dev/null || web_user="$(stat -c '%U' "${app_paths[0]}/public/index.php")"

# Composer needs a home it can write to when running as the web user.
export COMPOSER_HOME="${COMPOSER_HOME:-/var/www/.composer}"
mkdir -p "$COMPOSER_HOME"
chown -R "$web_user":"$web_user" "$COMPOSER_HOME"

print header "smart-connect upgrade"
say info "Web user: ${web_user}"
say info "Log: ${log_file}"
print info "Instances to upgrade (${#app_paths[@]}):"
for p in "${app_paths[@]}"; do
    print info "  - ${p}"
done

if [ ${#app_paths[@]} -gt 1 ]; then
    ask_yes_no "Upgrade all ${#app_paths[@]} instances" "yes" || die "Aborted."
fi

# ------------------------------------------------------------------- run ----

declare -a upgraded=()
declare -a failed=()
declare -a dumped_dbs=()

for i in "${!app_paths[@]}"; do
    if upgrade_instance "${app_paths[$i]}" "$((i + 1))" "${#app_paths[@]}"; then
        upgraded+=("${app_paths[$i]}")
    else
        failed+=("${app_paths[$i]}")
    fi
done

[ -n "$temp_dir" ] && rm -rf "$temp_dir"

# --------------------------------------------------------------- web tier ---

print header "Reloading the web server"

if command -v apache2ctl &>/dev/null; then
    if apache2ctl -t 2>>"$log_file"; then
        apache2ctl -k graceful 2>>"$log_file" || systemctl reload apache2 2>>"$log_file" ||
            say warning "Could not reload Apache. Do it by hand."
        say success "Apache reloaded."
    else
        say warning "apache2 config test failed. NOT reloading. Fix it and reload manually."
    fi
fi

# php-fpm keeps its own opcache, so a graceful Apache reload is not enough where
# the app runs behind fpm rather than mod_php.
for unit in $(systemctl list-units --type=service --state=running --no-legend 'php*-fpm.service' 2>/dev/null | awk '{print $1}'); do
    systemctl reload "$unit" 2>>"$log_file" && say success "Reloaded ${unit}."
done

# ---------------------------------------------------------------- summary ---

print header "Upgrade summary"

for p in "${upgraded[@]}"; do
    app_path="$p"
    print success "  ✓ ${p} at $(installed_ref)"
done
for p in "${failed[@]}"; do
    print error "  ✗ ${p}"
done

print info "Backups: ${BACKUP_ROOT}"
print info "Log: ${log_file}"
log_action "Upgrade complete. Updated: ${#upgraded[@]}, Failed: ${#failed[@]}"

[ ${#failed[@]} -eq 0 ]

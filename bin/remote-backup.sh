#!/bin/bash
set -Eeuo pipefail

# Sets up automatic off-machine backups of a Smart Connect installation.
#
# Run it straight from the repo:
#
#   sudo bash -c "$(curl -fsSL "https://raw.githubusercontent.com/deforay/smart-connect/master/bin/remote-backup.sh?v=$(date +%s)")"
#
# Note the shape. The script is an argument, not stdin. Piping it in as
# `curl ... | sudo bash` breaks the prompts, because sudo then feeds the script
# itself to the prompts.
#
# Two things are backed up, and nothing else:
#
#   1. The database, dumped fresh on every run with the app's own db-tools
#   2. config/autoload, which carries the DSN, the database credentials, and
#      the enrollment key every connected laboratory authenticates against
#
# The application code is not backed up. It comes back from git, and bin/upgrade.sh
# restores a deployment from those two pieces plus a checkout.
#
# One script covers all three destinations:
#   1. Another Linux machine, over SSH
#   2. A Windows shared folder, over SMB
#   3. A USB or external drive plugged into this machine
#
# Answers are saved to /etc/smart-connect/backup.conf, so re-running this script
# is a matter of pressing Enter through the prompts. The backup runner installed
# at /usr/local/bin/smart-connect-backup.sh reads that file, so nothing is
# hard-coded into the runner and it can be replaced without losing the settings.

trap 'echo -e "\033[1;91m❌ Error:\033[0m setup failed at line $LINENO (status $?)"' ERR

CONF_DIR="/etc/smart-connect"
CONF_FILE="${CONF_DIR}/backup.conf"
RUNNER="/usr/local/bin/smart-connect-backup.sh"
INSTANCE_UUID_FILE="${CONF_DIR}/instance-uuid"
SSH_KEY="/root/.ssh/id_ed25519_smart_connect"
MOUNT_POINT="/mnt/smart-connect-backup"
SMB_CRED_FILE="${CONF_DIR}/smb-backup.cred"
STAGING_DIR="/var/smart-connect-backup/staging"

# --- helpers ------------------------------------------------------------------

print() {
  local type=${1:-info}; shift || true
  local message=${1:-};  shift || true
  case "$type" in
    error)   printf "\033[1;91m❌ Error:\033[0m %s\n" "$message" ;;
    success) printf "\033[1;92m✅ Success:\033[0m %s\n" "$message" ;;
    warning) printf "\033[1;93m⚠️ Warning:\033[0m %s\n" "$message" ;;
    info)    printf "\033[1;96mℹ️ Info:\033[0m %s\n" "$message" ;;
    header)
      local term_width msg_length padding pad_str
      term_width=$(tput cols 2>/dev/null || echo 80)
      msg_length=${#message}
      padding=$(((term_width - msg_length) / 2)); ((padding<0)) && padding=0
      pad_str=$(printf '%*s' "$padding" '')
      printf "\n\033[1;96m%*s\033[0m\n" "$term_width" '' | tr ' ' '='
      printf "\033[1;96m%s%s\033[0m\n" "$pad_str" "$message"
      printf "\033[1;96m%*s\033[0m\n\n" "$term_width" '' | tr ' ' '='
      ;;
    *)       printf "%s\n" "$message" ;;
  esac
}

require_cmd() { command -v "$1" >/dev/null 2>&1 || { print error "Missing dependency: $1"; exit 1; }; }

trim() {
  local s=$1
  s="${s#"${s%%[![:space:]]*}"}"
  s="${s%"${s##*[![:space:]]}"}"
  printf '%s' "$s"
}

no_more_input() {
  print error "Ran out of answers. The input ended before the questions did."
  print info  "Run this directly in a terminal: sudo bin/remote-backup.sh"
  exit 1
}

# ask <var> <prompt> [default] — keeps asking until a non-empty answer is given.
ask() {
  local __var=$1 prompt=$2 default=${3:-} raw input rc
  while true; do
    rc=0; raw=""
    if [ -n "$default" ]; then
      read -r -p "$prompt [$default]: " raw || rc=$?
    else
      read -r -p "$prompt: " raw || rc=$?
    fi
    # An empty answer plus a read error means the input ended. Never fall back to
    # the default there: silently choosing an answer nobody gave is how the wrong
    # destination gets configured.
    [ -z "$raw" ] && [ "$rc" -ne 0 ] && no_more_input
    input="$(trim "${raw:-$default}")"
    [ -n "$input" ] && break
    print warning "This cannot be left empty. Try again."
  done
  printf -v "$__var" '%s' "$input"
}

ask_secret() {
  local __var=$1 prompt=$2 input rc
  while true; do
    rc=0
    read -r -s -p "$prompt: " input || rc=$?; echo
    [ -n "$input" ] && break
    [ "$rc" -ne 0 ] && no_more_input
    print warning "This cannot be left empty. Try again."
  done
  printf -v "$__var" '%s' "$input"
}

confirm() {
  local prompt=$1 answer rc=0
  read -r -p "$prompt (y/N): " answer || rc=$?
  [ "$rc" -ne 0 ] && [ -z "$answer" ] && no_more_input
  [[ "$answer" =~ ^[Yy]$ ]]
}

# --- preflight ----------------------------------------------------------------

if [ "$(id -u)" -ne 0 ]; then
  echo "Need admin privileges. Run with sudo."
  exit 1
fi

require_cmd realpath
require_cmd awk
require_cmd sed
require_cmd tar

mkdir -p "$CONF_DIR"
chmod 700 "$CONF_DIR"

# Load any previous answers so a re-run is press-Enter-all-the-way.
INSTANCE_NAME=""; APP_PATH=""; DEST_MODE=""
SSH_USER=""; SSH_HOST=""; SSH_PORT=""
SMB_HOST=""; SMB_SHARE=""; SMB_USER=""; SMB_VERS=""
LOCAL_ROOT=""; RETENTION=""; SCHEDULE_CRON=""
if [ -f "$CONF_FILE" ]; then
  # shellcheck disable=SC1090
  . "$CONF_FILE"
  print info "Found an existing configuration at $CONF_FILE. Press Enter to keep each saved answer."
fi

print header "Smart Connect backup setup"

# --- instance name ------------------------------------------------------------

ask INSTANCE_NAME "Name for this dashboard (country or site)" "${INSTANCE_NAME:-$(hostname -s 2>/dev/null || echo smart-connect)}"
SANITIZED_NAME=$(printf '%s' "$INSTANCE_NAME" | tr -s '[:space:]' '-' | tr -cd '[:alnum:]-' | sed 's/-*$//;s/^-*//')
if [ -z "$SANITIZED_NAME" ]; then
  print error "That name has no letters or numbers in it. Use something like 'kenya-national'."
  exit 1
fi

# --- installation identity ----------------------------------------------------
# Every installation gets a permanent UUID. The destination folder is named from
# it, so two dashboards that pick the same name still get separate folders and
# can never overwrite each other.

UUID_IS_NEW=0
if [ ! -f "$INSTANCE_UUID_FILE" ]; then
  INSTANCE_UUID="$(cat /proc/sys/kernel/random/uuid)"
  printf '%s\n' "$INSTANCE_UUID" > "$INSTANCE_UUID_FILE"
  chmod 600 "$INSTANCE_UUID_FILE"
  UUID_IS_NEW=1
else
  INSTANCE_UUID="$(trim "$(cat "$INSTANCE_UUID_FILE")")"
fi
UUID_SHORT="${INSTANCE_UUID:0:8}"
DEST_FOLDER="${SANITIZED_NAME}-${UUID_SHORT}"

print success "This installation is '${SANITIZED_NAME}' (id ${UUID_SHORT})"
print info    "Its backups will live in a folder called: ${DEST_FOLDER}"

# --- installation path --------------------------------------------------------

looks_like_smart_connect() {
  [ -f "$1/config/autoload/global.php" ] && [ -d "$1/public" ] &&
    grep -q 'deforay/smart-connect' "$1/composer.json" 2>/dev/null
}

print header "Which installation should be backed up?"

if [ -z "$APP_PATH" ]; then
  for candidate in /var/www/smart-connect /var/www/smartconnect; do
    if looks_like_smart_connect "$candidate"; then APP_PATH="$candidate"; break; fi
  done
fi
if [ -z "$APP_PATH" ]; then
  for candidate in /var/www/*/; do
    candidate="${candidate%/}"
    if looks_like_smart_connect "$candidate"; then APP_PATH="$candidate"; break; fi
  done
fi
[ -n "$APP_PATH" ] && print info "Detected an installation at $APP_PATH"

while true; do
  ask APP_PATH "Smart Connect folder path" "${APP_PATH:-/var/www/smart-connect}"
  [[ "$APP_PATH" != /* ]] && APP_PATH="$(realpath "$APP_PATH" 2>/dev/null || printf '%s' "$APP_PATH")"
  if [ ! -d "$APP_PATH" ]; then
    print warning "'$APP_PATH' does not exist. Try again."
    APP_PATH=""
    continue
  fi
  if ! looks_like_smart_connect "$APP_PATH"; then
    print warning "'$APP_PATH' does not look like a Smart Connect installation (no config/autoload/global.php)."
    APP_PATH=""
    continue
  fi
  break
done
print success "Backing up: $APP_PATH"

# --- the database it talks to -------------------------------------------------
# Read from the same merged config the application uses, so setup fails here
# rather than at 2am if the credentials are wrong.

PHP_BIN="$(command -v php || true)"
[ -n "$PHP_BIN" ] || { print error "php is not installed, so the database cannot be dumped."; exit 1; }

DB_NAME="$("$PHP_BIN" -r '
$dir = $argv[1] . "/config/autoload";
$merged = [];
foreach (glob($dir . "/{{,*.}global,{,*.}local}.php", GLOB_BRACE) ?: [] as $f) {
    $c = @include $f;
    if (is_array($c)) { $merged = array_replace_recursive($merged, $c); }
}
$db = $merged["db"] ?? [];
$dsn = (string) ($db["dsn"] ?? "");
$name = preg_match("/dbname=([^;]+)/", $dsn, $m) ? trim($m[1]) : (string) ($db["data-base-name"] ?? "");
echo $name;
' "$APP_PATH" 2>/dev/null || true)"

if [ -z "$DB_NAME" ]; then
  print warning "Could not read the database name from ${APP_PATH}/config/autoload."
  confirm "Continue anyway? The first backup will show whether it works" || exit 1
else
  print success "Database to back up: ${DB_NAME}"
fi

# --- destination --------------------------------------------------------------

print header "Where should the backup be sent?"
echo "  1) Another Linux machine on the network (over SSH)"
echo "  2) A shared folder on a Windows machine (over SMB)"
echo "  3) A USB or external drive plugged into this machine"
echo

default_choice=1
case "$DEST_MODE" in
  ssh)   default_choice=1 ;;
  smb)   default_choice=2 ;;
  local) default_choice=3 ;;
esac

while true; do
  ask choice "Choose 1, 2 or 3" "$default_choice"
  case "$choice" in
    1) DEST_MODE="ssh";   break ;;
    2) DEST_MODE="smb";   break ;;
    3) DEST_MODE="local"; break ;;
    *) print warning "Enter 1, 2 or 3." ;;
  esac
done

# --- destination: another Linux machine over SSH ------------------------------

configure_ssh() {
  require_cmd ssh
  require_cmd ssh-keygen
  require_cmd ssh-copy-id

  print header "Backup server details"

  mkdir -p /root/.ssh; chmod 700 /root/.ssh
  if [ ! -f "$SSH_KEY" ]; then
    print info "Creating a dedicated SSH key for backups..."
    ssh-keygen -t ed25519 -C "smart-connect-backup-${SANITIZED_NAME}" -N "" -f "$SSH_KEY" >/dev/null
  fi
  chmod 600 "$SSH_KEY"; chmod 644 "${SSH_KEY}.pub"

  while true; do
    ask SSH_USER "Username on the backup server" "${SSH_USER:-}"
    ask SSH_HOST "Hostname or IP of the backup server" "${SSH_HOST:-}"
    ask SSH_PORT "SSH port" "${SSH_PORT:-22}"

    if ! [[ "$SSH_PORT" =~ ^[0-9]+$ ]] || [ "$SSH_PORT" -lt 1 ] || [ "$SSH_PORT" -gt 65535 ]; then
      print warning "'$SSH_PORT' is not a valid port number."
      SSH_PORT=""
      continue
    fi

    print info "Checking that ${SSH_HOST}:${SSH_PORT} is reachable..."
    if ! timeout 10 bash -c "</dev/tcp/${SSH_HOST}/${SSH_PORT}" 2>/dev/null; then
      print warning "Cannot reach ${SSH_HOST} on port ${SSH_PORT}."
      print info    "Check that the machine is switched on, reachable from here, and that its SSH port is open."
      confirm "Try different details?" && { SSH_HOST=""; SSH_PORT=""; continue; }
      exit 1
    fi
    print success "Backup server is reachable"

    # Already trusted from a previous run?
    if ssh -n -i "$SSH_KEY" -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10 \
         -p "$SSH_PORT" "${SSH_USER}@${SSH_HOST}" true 2>/dev/null; then
      print success "Password-free login already works"
      break
    fi

    print info "Installing the backup key on the server. You will be asked for ${SSH_USER}'s password once."
    if ! ssh-copy-id -i "${SSH_KEY}.pub" -o StrictHostKeyChecking=accept-new \
         -p "$SSH_PORT" "${SSH_USER}@${SSH_HOST}" >/dev/null; then
      print warning "Could not install the key. The username or password may be wrong, or the server may not allow password logins."
      confirm "Try again?" && { SSH_USER=""; SSH_HOST=""; SSH_PORT=""; continue; }
      exit 1
    fi

    if ! ssh -n -i "$SSH_KEY" -o BatchMode=yes -o ConnectTimeout=10 \
         -p "$SSH_PORT" "${SSH_USER}@${SSH_HOST}" true; then
      print warning "The key was installed but password-free login still does not work."
      confirm "Try again?" && continue
      exit 1
    fi
    print success "Password-free login works"
    break
  done

  local remote_home
  remote_home="$(ssh -n -i "$SSH_KEY" -o BatchMode=yes -p "$SSH_PORT" "${SSH_USER}@${SSH_HOST}" 'printf %s "$HOME"')"
  DEST_BASE="${remote_home}/smart-connect-backups"
  DEST_DIR="${DEST_BASE}/${DEST_FOLDER}"
}

# --- destination: Windows shared folder over SMB ------------------------------

configure_smb() {
  print header "Windows shared folder details"
  print info "On the Windows machine: share a folder (for example C:\\SmartConnect-Backups)"
  print info "and give a Windows user Change + Read permission on that share."
  echo

  print info "Installing the tools needed to talk to Windows shares..."
  apt-get update -y >/dev/null 2>&1 || print warning "Could not refresh the package lists. Continuing."
  apt-get install -y cifs-utils rsync >/dev/null || { print error "Could not install cifs-utils. Fix the package manager and re-run."; exit 1; }
  require_cmd mount.cifs

  mkdir -p "$MOUNT_POINT"

  local smb_pass
  while true; do
    ask SMB_HOST  "Windows hostname or IP (for example 192.168.1.50)" "${SMB_HOST:-}"
    ask SMB_SHARE "Name of the shared folder (for example SmartConnect-Backups)" "${SMB_SHARE:-}"
    ask SMB_USER  "Windows username" "${SMB_USER:-}"
    ask_secret smb_pass "Windows password for ${SMB_USER}"

    if printf '%s' "$SMB_SHARE" | grep -q ' '; then
      print warning "The share name contains a space. Re-share the folder using a name without spaces, such as SmartConnect-Backups."
      SMB_SHARE=""
      continue
    fi

    umask 077
    printf 'username=%s\npassword=%s\n' "$SMB_USER" "$smb_pass" > "$SMB_CRED_FILE"
    chmod 600 "$SMB_CRED_FILE"
    umask 022

    local unc="//${SMB_HOST}/${SMB_SHARE}"
    mountpoint -q "$MOUNT_POINT" && umount "$MOUNT_POINT" 2>/dev/null || true

    # Try the modern dialect first and fall back, so the operator never has to
    # know what an "SMB protocol version" is.
    local mounted=0 v
    for v in "${SMB_VERS:-3.1.1}" 3.0 2.1 1.0; do
      if mount -t cifs "$unc" "$MOUNT_POINT" \
           -o "credentials=${SMB_CRED_FILE},vers=${v},uid=root,gid=root,iocharset=utf8,file_mode=0600,dir_mode=0700" 2>/dev/null; then
        SMB_VERS="$v"; mounted=1; break
      fi
    done

    if [ "$mounted" -ne 1 ]; then
      print warning "Could not connect to ${unc}."
      print info    "Check the username and password, that the folder is actually shared,"
      print info    "and that File and Printer Sharing is allowed through the Windows firewall."
      rm -f "$SMB_CRED_FILE"
      confirm "Try again?" && { SMB_HOST=""; SMB_SHARE=""; SMB_USER=""; SMB_VERS=""; continue; }
      exit 1
    fi

    if ! ( : > "${MOUNT_POINT}/.smart-connect-writetest" && rm -f "${MOUNT_POINT}/.smart-connect-writetest" ); then
      print warning "Connected, but the folder is read-only. Give ${SMB_USER} the Change permission on the share in Windows."
      umount "$MOUNT_POINT" 2>/dev/null || true
      confirm "Try again?" && continue
      exit 1
    fi

    print success "Connected to ${unc} (SMB ${SMB_VERS}) and it is writable"
    break
  done

  # Remount automatically after a reboot.
  local fstab_line="//${SMB_HOST}/${SMB_SHARE} ${MOUNT_POINT} cifs credentials=${SMB_CRED_FILE},vers=${SMB_VERS},uid=root,gid=root,iocharset=utf8,file_mode=0600,dir_mode=0700,nofail,_netdev 0 0"
  if grep -qE "[[:space:]]${MOUNT_POINT}[[:space:]]" /etc/fstab; then
    sed -i "\#[[:space:]]${MOUNT_POINT}[[:space:]]#d" /etc/fstab
  fi
  echo "$fstab_line" >> /etc/fstab
  print success "The share will reconnect by itself after a restart"

  DEST_BASE="${MOUNT_POINT}/smart-connect-backups"
  DEST_DIR="${DEST_BASE}/${DEST_FOLDER}"
}

# --- destination: local USB or external drive ---------------------------------

configure_local() {
  print header "External drive details"
  print info "Plug the drive in and make sure it is mounted before continuing."
  if command -v lsblk >/dev/null 2>&1; then
    echo
    lsblk -o NAME,SIZE,FSTYPE,MOUNTPOINT 2>/dev/null | grep -v '^loop' || true
    echo
  fi

  while true; do
    ask LOCAL_ROOT "Folder on the drive to back up into" "${LOCAL_ROOT:-/media/backup}"
    if [ ! -d "$LOCAL_ROOT" ]; then
      print warning "'$LOCAL_ROOT' does not exist. Is the drive plugged in and mounted?"
      LOCAL_ROOT=""
      continue
    fi
    if ! ( : > "${LOCAL_ROOT}/.smart-connect-writetest" && rm -f "${LOCAL_ROOT}/.smart-connect-writetest" ); then
      print warning "'$LOCAL_ROOT' is not writable."
      LOCAL_ROOT=""
      continue
    fi
    # A backup on the same disk as the original is not a backup.
    if [ "$(stat -c %d "$LOCAL_ROOT" 2>/dev/null || echo 0)" = "$(stat -c %d "$APP_PATH" 2>/dev/null || echo 1)" ]; then
      print warning "'$LOCAL_ROOT' is on the same disk as the installation, so it would not survive a disk failure."
      confirm "Use it anyway?" || { LOCAL_ROOT=""; continue; }
    fi
    break
  done

  print success "Backing up to $LOCAL_ROOT"
  DEST_BASE="${LOCAL_ROOT}/smart-connect-backups"
  DEST_DIR="${DEST_BASE}/${DEST_FOLDER}"
}

case "$DEST_MODE" in
  ssh)   configure_ssh ;;
  smb)   configure_smb ;;
  local) configure_local ;;
esac

# --- dest_exec ----------------------------------------------------------------
# Runs a command wherever the backup lands, so the folder handling below is
# written once instead of once per destination type.

dest_exec() {
  case "$DEST_MODE" in
    ssh) ssh -n -i "$SSH_KEY" -o BatchMode=yes -o ConnectTimeout=10 -p "$SSH_PORT" "${SSH_USER}@${SSH_HOST}" "$1" ;;
    *)   bash -c "$1" ;;
  esac
}

# --- taking over an existing backup folder ------------------------------------
# A rebuilt server has no identity file, so it would be handed a brand new folder
# and would never see the backups it needs to recover from. When the identity is
# new and the destination already holds folders, offer them.

ADOPTED=0
if [ "$UUID_IS_NEW" -eq 1 ]; then
  q_base="$(printf '%q' "$DEST_BASE")"
  mapfile -t EXISTING < <(dest_exec "ls -1 ${q_base} 2>/dev/null || true" | tr -d '\r' | sed '/^$/d')

  if [ "${#EXISTING[@]}" -gt 0 ]; then
    print header "There are already backups at this destination"
    print info "This server has no backup identity of its own yet, which is what a"
    print info "rebuilt server looks like."
    echo
    i=1
    for folder in "${EXISTING[@]}"; do
      q_folder="$(printf '%q' "${DEST_BASE}/${folder}")"
      meta="$(dest_exec "cat ${q_folder}/.instance-meta 2>/dev/null || true" | tr -d '\r')"
      who="$(printf '%s' "$meta" | awk -F= '/^instance=/{print $2}')"
      where="$(printf '%s' "$meta" | awk -F= '/^hostname=/{print $2}')"
      newest="$(dest_exec "ls -1 ${q_folder} 2>/dev/null | grep -E '^[0-9]{8}-[0-9]{6}$' | sort | tail -1" | tr -d '\r')"
      printf "  %d) %s\n" "$i" "$folder"
      printf "     %s on %s, newest backup %s\n" "${who:-unknown}" "${where:-unknown}" "${newest:-none}"
      i=$((i + 1))
    done
    echo
    print info "Choose one only if this server is that installation rebuilt."
    ask adopt_choice "Number to take over, or 0 to start a new folder" "0"

    if [[ "$adopt_choice" =~ ^[0-9]+$ ]] && [ "$adopt_choice" -ge 1 ] && [ "$adopt_choice" -le "${#EXISTING[@]}" ]; then
      DEST_FOLDER="${EXISTING[$((adopt_choice - 1))]}"
      q_folder="$(printf '%q' "${DEST_BASE}/${DEST_FOLDER}")"
      adopted_uuid="$(dest_exec "awk -F= '/^instance_uuid=/{print \$2}' ${q_folder}/.instance-meta 2>/dev/null || true" | tr -d '\r\n')"
      if [ -z "$adopted_uuid" ]; then
        print error "The folder ${DEST_FOLDER} carries no identity marker, so it cannot be taken over."
        exit 1
      fi
      INSTANCE_UUID="$adopted_uuid"
      printf '%s\n' "$INSTANCE_UUID" > "$INSTANCE_UUID_FILE"
      chmod 600 "$INSTANCE_UUID_FILE"
      UUID_SHORT="${INSTANCE_UUID:0:8}"
      ADOPTED=1
      print success "Taking over ${DEST_FOLDER}. Its history stays where it is."
    fi
  fi
fi

DEST_DIR="${DEST_BASE}/${DEST_FOLDER}"

# --- how much history to keep -------------------------------------------------

while true; do
  ask RETENTION "How many backups to keep at the destination" "${RETENTION:-14}"
  [[ "$RETENTION" =~ ^[0-9]+$ ]] && [ "$RETENTION" -ge 1 ] && break
  print warning "Enter a whole number of 1 or more."
  RETENTION=""
done

# --- how often ----------------------------------------------------------------

print header "How often should the backup run?"
echo "  1) Every 6 hours"
echo "  2) Every 12 hours"
echo "  3) Once a day, at 02:30"
echo

sched_default=2
case "$SCHEDULE_CRON" in
  "0 */6 * * *")  sched_default=1 ;;
  "0 */12 * * *") sched_default=2 ;;
  "30 2 * * *")   sched_default=3 ;;
esac

while true; do
  ask sched_choice "Choose 1, 2 or 3" "$sched_default"
  case "$sched_choice" in
    1) SCHEDULE_CRON="0 */6 * * *";  SCHEDULE_TEXT="every 6 hours";      break ;;
    2) SCHEDULE_CRON="0 */12 * * *"; SCHEDULE_TEXT="every 12 hours";     break ;;
    3) SCHEDULE_CRON="30 2 * * *";   SCHEDULE_TEXT="once a day at 02:30"; break ;;
    *) print warning "Enter 1, 2 or 3." ;;
  esac
done

# --- destination folder -------------------------------------------------------

print header "Preparing the backup folder"

q_dest="$(printf '%q' "$DEST_DIR")"
q_meta="$(printf '%q' "${DEST_DIR}/.instance-meta")"

if dest_exec "test -d ${q_dest}" 2>/dev/null; then
  remote_uuid="$(dest_exec "awk -F= '/^instance_uuid=/{print \$2}' ${q_meta} 2>/dev/null || true" | tr -d '\r\n')"
  if [ -n "$remote_uuid" ] && [ "$remote_uuid" != "$INSTANCE_UUID" ]; then
    # Effectively unreachable now that folders carry the UUID, but a wrong answer
    # here would overwrite another dashboard's backup, so refuse rather than guess.
    print error "The folder ${DEST_FOLDER} already belongs to a different installation."
    print info  "Nothing has been changed. Contact support before continuing."
    exit 1
  fi
  print info "Reusing the existing folder for this installation"
fi

dest_exec "mkdir -p ${q_dest} && printf 'instance_uuid=%s\ninstance=%s\nhostname=%s\nupdated_at=%s\n' \
  $(printf '%q' "$INSTANCE_UUID") $(printf '%q' "$SANITIZED_NAME") $(printf '%q' "$(hostname -f 2>/dev/null || hostname)") $(printf '%q' "$(date -u +%FT%TZ)") > ${q_meta}"
print success "Backup folder ready: ${DEST_DIR}"

# --- tools --------------------------------------------------------------------

if ! command -v rsync >/dev/null 2>&1; then
  print info "Installing rsync..."
  apt-get update -y >/dev/null 2>&1 || true
  apt-get install -y rsync >/dev/null || { print error "Could not install rsync."; exit 1; }
fi
require_cmd rsync

mkdir -p "$STAGING_DIR"
chmod 700 "$(dirname "$STAGING_DIR")"

# --- save configuration -------------------------------------------------------

umask 077
cat > "$CONF_FILE" <<CONF
# Smart Connect backup configuration. Written by bin/remote-backup.sh.
# Re-run that script to change any of this.
INSTANCE_NAME='${SANITIZED_NAME}'
INSTANCE_UUID='${INSTANCE_UUID}'
DEST_FOLDER='${DEST_FOLDER}'
APP_PATH='${APP_PATH}'
DEST_MODE='${DEST_MODE}'
DEST_BASE='${DEST_BASE}'
DEST_DIR='${DEST_DIR}'
SSH_USER='${SSH_USER}'
SSH_HOST='${SSH_HOST}'
SSH_PORT='${SSH_PORT}'
SSH_KEY='${SSH_KEY}'
SMB_HOST='${SMB_HOST}'
SMB_SHARE='${SMB_SHARE}'
SMB_USER='${SMB_USER}'
SMB_VERS='${SMB_VERS}'
SMB_CRED_FILE='${SMB_CRED_FILE}'
MOUNT_POINT='${MOUNT_POINT}'
LOCAL_ROOT='${LOCAL_ROOT}'
STAGING_DIR='${STAGING_DIR}'
RETENTION='${RETENTION}'
LOCAL_KEEP='2'
SCHEDULE_CRON='${SCHEDULE_CRON}'
VERIFY_CHECKSUMS='yes'
# Set a passphrase here to encrypt the database dump with GPG. Leave it empty for
# plain archives. A passphrase kept only on this machine is lost with this
# machine, and the backup is then unreadable, so store it somewhere else too.
BACKUP_ENCRYPT_PASSWORD=''
CONF
chmod 600 "$CONF_FILE"
umask 022
print success "Settings saved to $CONF_FILE"

# --- install the backup runner ------------------------------------------------

print header "Installing the backup runner"

cat > "$RUNNER" <<'RUNNER_SCRIPT'
#!/bin/bash
# Smart Connect backup runner. Installed by bin/remote-backup.sh; reads its
# settings from /etc/smart-connect/backup.conf. Safe to run by hand at any time.
#
# Each run produces one dated folder at the destination holding a fresh database
# dump and a copy of config/autoload. Folders are never overwritten, so a run
# that copies a corrupt dump cannot destroy the good one before it.
#
# It is called from root's crontab directly, not through the application's
# cron.sh, because the backup has to keep working on a day when the application
# does not.
set -Eeuo pipefail

CONF_FILE="/etc/smart-connect/backup.conf"
STATE_DIR="/var/lib/smart-connect"
STATUS_JSON="${STATE_DIR}/backup-status.json"
STATUS_ENV="${STATE_DIR}/backup-status.env"
LOGFILE="/var/log/smart-connect-backup.log"
LOCKFILE="/var/lock/smart-connect-backup.lock"
RESTORE_ROOT="/var/smart-connect-backup/restore"
SAFETY_ROOT="/var/smart-connect-backup/before-restore"

usage() {
  cat <<USAGE
Smart Connect backup runner.

  smart-connect-backup.sh                 Run a backup now
  smart-connect-backup.sh --status        Show when the last backup ran and whether it worked
  smart-connect-backup.sh --test          Check the connection and the database, changing nothing
  smart-connect-backup.sh --list          List the backups held at the destination
  smart-connect-backup.sh --restore [WHICH] [--yes]
                                          Restore the database and config/autoload.
                                          WHICH is a folder name from --list, or "latest"
  smart-connect-backup.sh --disable       Stop the scheduled backups
  smart-connect-backup.sh --enable        Start the scheduled backups again
  smart-connect-backup.sh --help          Show this message
USAGE
}

ACTION="run"
RESTORE_WHICH="latest"
ASSUME_YES=0
case "${1:-}" in
  "")         ACTION="run" ;;
  --status)   ACTION="status" ;;
  --test)     ACTION="test" ;;
  --list)     ACTION="list" ;;
  --disable)  ACTION="disable" ;;
  --enable)   ACTION="enable" ;;
  --restore)
    ACTION="restore"
    shift
    if [ $# -gt 0 ] && [ "${1:-}" != "--yes" ] && [ "${1:-}" != "-y" ]; then
      RESTORE_WHICH="$1"; shift
    fi
    if [ "${1:-}" = "--yes" ] || [ "${1:-}" = "-y" ]; then
      ASSUME_YES=1
    fi
    ;;
  --help|-h)  usage; exit 0 ;;
  *)          echo "Unknown option: $1"; usage; exit 2 ;;
esac

[ -f "$CONF_FILE" ] || { echo "No backup configuration found at $CONF_FILE. Run bin/remote-backup.sh first."; exit 1; }
# shellcheck disable=SC1090
. "$CONF_FILE"

: "${INSTANCE_NAME:=}"; : "${INSTANCE_UUID:=}"; : "${DEST_FOLDER:=}"; : "${APP_PATH:=}"
: "${DEST_MODE:=}"; : "${DEST_BASE:=}"; : "${DEST_DIR:=}"
: "${SSH_USER:=}"; : "${SSH_HOST:=}"; : "${SSH_PORT:=22}"; : "${SSH_KEY:=/root/.ssh/id_ed25519_smart_connect}"
: "${SMB_HOST:=}"; : "${SMB_SHARE:=}"; : "${SMB_VERS:=3.0}"; : "${MOUNT_POINT:=/mnt/smart-connect-backup}"
: "${LOCAL_ROOT:=}"; : "${STAGING_DIR:=/var/smart-connect-backup/staging}"
: "${RETENTION:=14}"; : "${LOCAL_KEEP:=2}"; : "${VERIFY_CHECKSUMS:=yes}"
: "${SCHEDULE_CRON:=0 */12 * * *}"; : "${BACKUP_ENCRYPT_PASSWORD:=}"

CRON_MARKER="/usr/local/bin/smart-connect-backup.sh"

# --- scheduling toggles (no lock or logging needed) ---------------------------

case "$ACTION" in
  disable)
    if crontab -l 2>/dev/null | grep -q "$CRON_MARKER"; then
      crontab -l 2>/dev/null | grep -v "$CRON_MARKER" | crontab -
      echo "Scheduled backups stopped. Run 'smart-connect-backup.sh --enable' to start them again."
    else
      echo "Scheduled backups were already stopped."
    fi
    pkill -f "$CRON_MARKER" 2>/dev/null && echo "Stopped the backup that was running." || true
    exit 0
    ;;
  enable)
    ( crontab -l 2>/dev/null | grep -v "$CRON_MARKER" || true ) | crontab -
    ( crontab -l 2>/dev/null; echo "${SCHEDULE_CRON} ${CRON_MARKER} >/dev/null 2>&1" ) | crontab -
    echo "Scheduled backups started: ${SCHEDULE_CRON}"
    exit 0
    ;;
esac

# --- status readout -----------------------------------------------------------

human_age() {
  local secs=$1
  if   [ "$secs" -lt 3600 ];  then echo "$((secs / 60)) minutes ago"
  elif [ "$secs" -lt 86400 ]; then echo "$((secs / 3600)) hours ago"
  else                             echo "$((secs / 86400)) days ago"; fi
}

if [ "$ACTION" = "status" ]; then
  echo "Installation   : ${INSTANCE_NAME} (${DEST_FOLDER})"
  case "$DEST_MODE" in
    ssh)   echo "Backing up to  : ${SSH_USER}@${SSH_HOST}:${DEST_DIR}" ;;
    smb)   echo "Backing up to  : //${SMB_HOST}/${SMB_SHARE} -> ${DEST_DIR}" ;;
    local) echo "Backing up to  : ${DEST_DIR}" ;;
  esac
  if [ -f "$STATUS_ENV" ]; then
    # shellcheck disable=SC1090
    . "$STATUS_ENV"
    : "${LAST_STATUS:=unknown}"; : "${LAST_SUCCESS_AT:=}"; : "${LAST_SUCCESS_EPOCH:=0}"
    : "${LAST_FAILURE_AT:=}"; : "${LAST_ERROR:=}"; : "${LAST_SIZE:=unknown}"; : "${LAST_DURATION:=0}"
    : "${LAST_RUN_FOLDER:=}"; : "${LAST_DUMP:=}"
    if [ "${LAST_SUCCESS_EPOCH:-0}" -gt 0 ]; then
      age=$(( $(date +%s) - LAST_SUCCESS_EPOCH ))
      echo "Last good backup: ${LAST_SUCCESS_AT} ($(human_age "$age"))"
      echo "  folder        : ${LAST_RUN_FOLDER}"
      echo "  database dump : ${LAST_DUMP}"
      echo "Size at rest    : ${LAST_SIZE}"
      if [ "$age" -gt 172800 ]; then
        echo
        echo "The last good backup is more than two days old. Run --test to find out why."
      fi
    else
      echo "Last good backup: never"
    fi
    [ "$LAST_STATUS" = "failed" ] && { echo "Last attempt    : FAILED at ${LAST_FAILURE_AT}"; echo "Reason          : ${LAST_ERROR}"; }
    [ "$LAST_STATUS" = "ok" ]     &&   echo "Last attempt    : succeeded in ${LAST_DURATION}s"
  else
    echo "Last good backup: never (no backup has finished yet)"
  fi
  if crontab -l 2>/dev/null | grep -q "$CRON_MARKER"; then
    echo "Schedule        : ${SCHEDULE_CRON}"
  else
    echo "Schedule        : OFF — backups are not scheduled"
  fi
  echo "Keeping         : the last ${RETENTION} backups"
  exit 0
fi

# --- logging ------------------------------------------------------------------
# Appended, never truncated: the record of a failure must survive the next run.

mkdir -p "$STATE_DIR"
umask 027
touch "$LOGFILE" 2>/dev/null || true
chmod 640 "$LOGFILE" 2>/dev/null || true
exec 1> >(tee -a "$LOGFILE")
exec 2>&1

print() {
  local t=${1:-info}; shift || true
  local m=${1:-};     shift || true
  local ts="[$(date '+%Y-%m-%d %H:%M:%S')]"
  case "$t" in
    error)   printf "%s \033[1;91m❌ Error:\033[0m %s\n" "$ts" "$m" ;;
    success) printf "%s \033[1;92m✅ Success:\033[0m %s\n" "$ts" "$m" ;;
    warning) printf "%s \033[1;93m⚠️ Warning:\033[0m %s\n" "$ts" "$m" ;;
    info)    printf "%s \033[1;96mℹ️ Info:\033[0m %s\n" "$ts" "$m" ;;
    *)       printf "%s %s\n" "$ts" "$m" ;;
  esac
}

# --- status file --------------------------------------------------------------

LAST_STATUS="never"; LAST_SUCCESS_AT=""; LAST_SUCCESS_EPOCH=0
LAST_FAILURE_AT=""; LAST_ERROR=""; LAST_SIZE="unknown"; LAST_DURATION=0
LAST_RUN_FOLDER=""; LAST_DUMP=""; LAST_DUMP_BYTES=0
if [ -f "$STATUS_ENV" ]; then
  # shellcheck disable=SC1090
  . "$STATUS_ENV" || true
fi

json_escape() { printf '%s' "$1" | sed 's/\\/\\\\/g; s/"/\\"/g' | tr -d '\n\r'; }

write_status() {
  local st=$1 msg=${2:-} size=${3:-unknown} duration=${4:-0}
  local now epoch
  now="$(date -u +%FT%TZ)"; epoch="$(date +%s)"
  if [ "$st" = "ok" ]; then
    LAST_SUCCESS_AT="$now"; LAST_SUCCESS_EPOCH="$epoch"; LAST_SIZE="$size"; LAST_ERROR=""
  else
    LAST_FAILURE_AT="$now"; LAST_ERROR="$msg"
  fi
  LAST_STATUS="$st"; LAST_DURATION="$duration"

  mkdir -p "$STATE_DIR"
  umask 022
  cat > "$STATUS_ENV" <<STATUS
LAST_STATUS='${LAST_STATUS}'
LAST_RUN_AT='${now}'
LAST_SUCCESS_AT='${LAST_SUCCESS_AT}'
LAST_SUCCESS_EPOCH='${LAST_SUCCESS_EPOCH}'
LAST_FAILURE_AT='${LAST_FAILURE_AT}'
LAST_ERROR='$(printf '%s' "$LAST_ERROR" | tr -d "'" | tr -d '\n\r')'
LAST_SIZE='${LAST_SIZE}'
LAST_DURATION='${LAST_DURATION}'
LAST_RUN_FOLDER='${LAST_RUN_FOLDER}'
LAST_DUMP='${LAST_DUMP}'
LAST_DUMP_BYTES='${LAST_DUMP_BYTES}'
STATUS
  cat > "$STATUS_JSON" <<STATUS
{
  "instance": "$(json_escape "$INSTANCE_NAME")",
  "folder": "$(json_escape "$DEST_FOLDER")",
  "destination": "$(json_escape "$DEST_MODE")",
  "status": "$(json_escape "$LAST_STATUS")",
  "last_run_at": "${now}",
  "last_success_at": "$(json_escape "$LAST_SUCCESS_AT")",
  "last_success_epoch": ${LAST_SUCCESS_EPOCH:-0},
  "last_failure_at": "$(json_escape "$LAST_FAILURE_AT")",
  "last_error": "$(json_escape "$LAST_ERROR")",
  "last_run_folder": "$(json_escape "$LAST_RUN_FOLDER")",
  "last_dump": "$(json_escape "$LAST_DUMP")",
  "last_dump_bytes": ${LAST_DUMP_BYTES:-0},
  "size": "$(json_escape "$LAST_SIZE")",
  "duration_seconds": ${LAST_DURATION:-0}
}
STATUS
  chmod 644 "$STATUS_JSON" "$STATUS_ENV" 2>/dev/null || true
}

fail() {
  trap - ERR
  local msg=$1
  print error "$msg"
  [ "$ACTION" = "run" ] && write_status failed "$msg" "unknown" "${SECONDS:-0}"
  exit 1
}
trap 'fail "backup failed at line $LINENO (status $?)"' ERR

# --- one run at a time --------------------------------------------------------

exec 9>"$LOCKFILE"
if ! flock -n 9; then
  print warning "Another backup is already running. Leaving it to finish."
  exit 0
fi

# --- destination helpers ------------------------------------------------------

SSH_OPTS=(-o BatchMode=yes -o ConnectTimeout=10 -o StrictHostKeyChecking=accept-new)

dest_exec() {
  case "$DEST_MODE" in
    ssh) ssh -n -i "$SSH_KEY" "${SSH_OPTS[@]}" -p "$SSH_PORT" "${SSH_USER}@${SSH_HOST}" "$1" ;;
    *)   bash -c "$1" ;;
  esac
}

ensure_destination_available() {
  case "$DEST_MODE" in
    ssh)
      dest_exec "true" >/dev/null 2>&1 || fail "Cannot reach the backup server ${SSH_HOST}. Is it switched on and on the network?"
      ;;
    smb)
      if ! mountpoint -q "$MOUNT_POINT"; then
        print warning "The Windows shared folder is not connected. Reconnecting."
        mount "$MOUNT_POINT" >/dev/null 2>&1 || fail "Cannot connect to //${SMB_HOST}/${SMB_SHARE}. Is the Windows machine switched on and on the network?"
      fi
      ;;
    local)
      [ -d "$LOCAL_ROOT" ] || fail "The backup drive at ${LOCAL_ROOT} is not there. Is it plugged in?"
      ;;
  esac
}

Q_DEST="$(printf '%q' "$DEST_DIR")"

# Filesystems that cannot hold POSIX ownership, permissions, or symlinks need
# rsync told not to try.
needs_compat_flags() {
  local fstype
  fstype=$(stat -f -c %T "$1" 2>/dev/null || echo unknown)
  case "$fstype" in
    vfat|exfat|msdos|ntfs|fuseblk|cifs|smb2) return 0 ;;
    *) return 1 ;;
  esac
}

rsync_opts() {
  local opts=(--partial --timeout=900)
  case "$DEST_MODE" in
    ssh)
      opts+=(-a -e "ssh -i ${SSH_KEY} -o BatchMode=yes -o ConnectTimeout=10 -o StrictHostKeyChecking=accept-new -p ${SSH_PORT}")
      ;;
    smb)
      opts+=(-rt --no-perms --no-owner --no-group --omit-dir-times --modify-window=2)
      ;;
    local)
      if needs_compat_flags "$LOCAL_ROOT"; then
        opts+=(-rt --no-perms --no-owner --no-group --omit-dir-times --modify-window=2)
      else
        opts+=(-a)
      fi
      ;;
  esac
  printf '%s\n' "${opts[@]}"
}

# The dump is already compressed, so rsync compression only burns CPU.
mapfile -t RSYNC_OPTS < <(rsync_opts)

rsync_target_for() {
  case "$DEST_MODE" in
    ssh) printf '%s' "${SSH_USER}@${SSH_HOST}:$1" ;;
    *)   printf '%s' "$1" ;;
  esac
}

check_folder_ownership() {
  local remote_uuid
  remote_uuid="$(dest_exec "awk -F= '/^instance_uuid=/{print \$2}' ${Q_DEST}/.instance-meta 2>/dev/null || true" | tr -d '\r\n')"
  [ "$remote_uuid" = "$INSTANCE_UUID" ] || fail "The backup folder does not belong to this installation any more. Re-run bin/remote-backup.sh."
}

# --- the application it backs up ----------------------------------------------

PHP_BIN="$(command -v php || true)"
[ -n "$PHP_BIN" ] || fail "php is not installed, so the database cannot be dumped."
[ -d "$APP_PATH" ] || fail "The installation folder ${APP_PATH} does not exist."

# Reads the merged config the same way the application does, so the dump always
# targets the database the dashboard is actually using.
read_db_config() {
  "$PHP_BIN" -r '
$dir = $argv[1] . "/config/autoload";
$merged = [];
foreach (glob($dir . "/{{,*.}global,{,*.}local}.php", GLOB_BRACE) ?: [] as $f) {
    $c = @include $f;
    if (is_array($c)) { $merged = array_replace_recursive($merged, $c); }
}
$db = $merged["db"] ?? [];
$dsn = (string) ($db["dsn"] ?? "");
$host = preg_match("/host=([^;]+)/", $dsn, $m) ? trim($m[1]) : (string) ($db["data-base-host"] ?? "localhost");
$name = preg_match("/dbname=([^;]+)/", $dsn, $m) ? trim($m[1]) : (string) ($db["data-base-name"] ?? "");
$port = preg_match("/port=(\d+)/", $dsn, $m) ? (int) $m[1] : 3306;
$user = (string) ($db["username"] ?? $db["user"] ?? "root");
$pass = (string) ($db["password"] ?? "");
printf("DB_HOST=%s\nDB_PORT=%d\nDB_NAME=%s\nDB_USER=%s\nDB_PASS=%s\n", $host, $port, $name, $user, $pass);
' "$APP_PATH" 2>/dev/null
}

# The password goes in a file, not on a command line, where every user on the
# machine could read it out of the process list.
mysql_defaults_file() {
  local f
  f="$(mktemp /tmp/smart-connect-my.XXXXXX)"
  chmod 600 "$f"
  printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' "$DB_HOST" "$DB_PORT" "$DB_USER" "$DB_PASS" > "$f"
  printf '%s' "$f"
}

DB_HOST="localhost"; DB_PORT="3306"; DB_NAME=""; DB_USER=""; DB_PASS=""

load_db_config() {
  DB_HOST="localhost"; DB_PORT="3306"; DB_NAME=""; DB_USER=""; DB_PASS=""
  local key value
  while IFS='=' read -r key value; do
    case "$key" in
      DB_HOST) DB_HOST="$value" ;;
      DB_PORT) DB_PORT="$value" ;;
      DB_NAME) DB_NAME="$value" ;;
      DB_USER) DB_USER="$value" ;;
      DB_PASS) DB_PASS="$value" ;;
    esac
  done < <(read_db_config)
  [ -n "$DB_NAME" ]
}

# A restore is the one action that has to work on a machine whose configuration
# is not there yet. Putting it back is the first thing the restore does, and the
# credentials are read again once it has.
if ! load_db_config; then
  case "$ACTION" in
    run|test) fail "Could not read the database name from ${APP_PATH}/config/autoload." ;;
    *)        DB_NAME="" ;;
  esac
fi

# --- listing what is held at the destination ----------------------------------

list_runs() {
  dest_exec "ls -1 ${Q_DEST} 2>/dev/null | grep -E '^[0-9]{8}-[0-9]{6}$' | sort" | tr -d '\r'
}

if [ "$ACTION" = "list" ]; then
  ensure_destination_available
  check_folder_ownership
  runs="$(list_runs || true)"
  if [ -z "$runs" ]; then
    print warning "There are no backups at ${DEST_DIR} yet."
    exit 0
  fi
  print info "Backups at ${DEST_DIR}, oldest first:"
  echo
  while read -r run; do
    [ -n "$run" ] || continue
    size="$(dest_exec "du -sh $(printf '%q' "${DEST_DIR}/${run}") 2>/dev/null | cut -f1" | tr -d '\r\n' || echo "?")"
    printf '  %s   %s\n' "$run" "${size:-?}"
  done <<< "$runs"
  echo
  print info "Restore the newest one with: smart-connect-backup.sh --restore latest"
  exit 0
fi

# --- restoring ----------------------------------------------------------------

restore_from() {
  local which=$1 run
  ensure_destination_available
  check_folder_ownership

  if [ "$which" = "latest" ]; then
    run="$(list_runs | tail -1)"
    [ -n "$run" ] || fail "There are no backups at ${DEST_DIR} to restore from."
  else
    run="$which"
    dest_exec "test -d $(printf '%q' "${DEST_DIR}/${run}")" 2>/dev/null || fail "There is no backup called '${run}' at ${DEST_DIR}. Run --list to see what is there."
  fi

  print info "Restoring the backup taken at ${run}"
  print info "Into: ${APP_PATH} (database ${DB_NAME:-named in the backup})"

  local answer=""
  if [ "$ASSUME_YES" -ne 1 ]; then
    echo
    echo "This replaces the database '${DB_NAME:-named in the backup}' and the deployment files in"
    echo "${APP_PATH}/config/autoload with the copies taken at ${run}."
    read -r -p "Type the word restore to continue: " answer || true
    # Exit 3, not 0. A caller has to be able to tell a refusal, or an answer that
    # never arrived, from a restore that happened.
    [ "$answer" = "restore" ] || { print info "Nothing has been changed."; exit 3; }
  fi

  # Always fetch into an empty folder. Reusing a copy from an earlier restore
  # lets rsync skip a file whose size and timestamp happen to match, and the
  # checksums then belong to a different copy than the dump beside them.
  local local_copy="${RESTORE_ROOT}/${run}"
  rm -rf "$local_copy"
  mkdir -p "$local_copy"
  chmod 700 "$RESTORE_ROOT" "$local_copy"

  print info "Fetching the backup..."
  rsync "${RSYNC_OPTS[@]}" "$(rsync_target_for "${DEST_DIR}/${run}/")" "${local_copy}/" >/dev/null \
    || fail "Could not fetch the backup from the destination."

  if [ -f "${local_copy}/SHA256SUMS" ] && command -v sha256sum >/dev/null 2>&1; then
    ( cd "$local_copy" && sha256sum -c --quiet SHA256SUMS ) \
      || fail "The fetched backup does not match its checksums. It is damaged. Try an older one from --list."
    print success "Checksums match"
  fi

  local dump
  dump="$(find "${local_copy}/db" -maxdepth 1 -type f \( -name '*.sql' -o -name '*.sql.*' \) ! -name '*.meta.json' | sort | tail -1)"
  [ -n "$dump" ] || fail "The backup at ${run} holds no database dump."

  # A restore that goes wrong must leave a way back.
  local safety="${SAFETY_ROOT}/$(date +%Y%m%d-%H%M%S)"
  mkdir -p "$safety"
  chmod 700 "$SAFETY_ROOT" "$safety"
  print info "Copying the current database and config aside first..."
  dump_database "$safety" || print warning "Could not dump the current database. It may already be gone."
  tar -czf "${safety}/autoload.tar.gz" -C "$APP_PATH" config/autoload 2>/dev/null || true
  print success "Current state saved in ${safety}"

  # Config first: on a rebuilt machine the restored credentials are what lets the
  # database restore connect at all.
  print info "Restoring the deployment configuration..."
  local extract="${local_copy}/extracted"
  rm -rf "$extract"; mkdir -p "$extract"
  tar -xzf "${local_copy}/config/autoload.tar.gz" -C "$extract"
  local f base
  for f in "${extract}/config/autoload/global.php" "${extract}/config/autoload/local.php" \
           "${extract}/config/autoload/custom.global.php" "${extract}"/config/autoload/*.local.php; do
    [ -f "$f" ] || continue
    base="$(basename "$f")"
    cp -p "$f" "${APP_PATH}/config/autoload/${base}"
    print info "  restored config/autoload/${base}"
  done
  chown -R --reference="${APP_PATH}/public" "${APP_PATH}/config/autoload" 2>/dev/null || true

  # The credentials in memory are the ones from before the restore, and on a
  # rebuilt machine there were none. Read the restored ones before touching the
  # database, or the dump goes into the wrong place, or nowhere.
  load_db_config || fail "The restored configuration names no database. Check ${APP_PATH}/config/autoload/global.php."
  print info "Configuration restored. The database it names is ${DB_NAME}."

  print info "Restoring the database from $(basename "$dump")..."
  restore_dump "$dump" \
    || fail "The database restore failed. The database and config as they were before this are in ${safety}."

  verify_database_restored \
    || fail "The restore reported success but ${DB_NAME} holds no tables. What was there before is in ${safety}."

  [ -f "${APP_PATH}/bin/clear-config-cache.php" ] && ( cd "$APP_PATH" && "$PHP_BIN" bin/clear-config-cache.php >/dev/null 2>&1 || true )

  print success "Restored the backup taken at ${run}"
  print info    "Fetched copy   : ${local_copy}"
  print info    "Previous state : ${safety}"
  print info    "Check the dashboard in a browser, then run: smart-connect-backup.sh --test"
}

# --- dumping the database -----------------------------------------------------
# db-tools is the application's own backup tool, so its archives restore with
# `db-tools restore` on any machine. mysqldump is the fallback for a tree whose
# vendor/ is missing or broken, which is exactly when a backup matters most.

dump_database() {
  local out_dir=$1
  mkdir -p "$out_dir"

  if [ -f "${APP_PATH}/vendor/bin/db-tools" ]; then
    local args=(backup --output-dir="$out_dir" --retention=0)
    if [ -n "$BACKUP_ENCRYPT_PASSWORD" ]; then
      args+=(--encryption-password="$BACKUP_ENCRYPT_PASSWORD")
    else
      args+=(--no-encrypt)
    fi
    ( cd "$APP_PATH" && "$PHP_BIN" vendor/bin/db-tools "${args[@]}" ) && return 0
    print warning "db-tools could not dump the database. Falling back to mysqldump."
  else
    print warning "vendor/bin/db-tools is missing. Falling back to mysqldump."
  fi

  command -v mysqldump >/dev/null 2>&1 || return 1

  local defaults
  defaults="$(mysql_defaults_file)"

  local target="${out_dir}/${DB_NAME}-$(date -u +%Y%m%d-%H%M%S).sql.gz"
  if mysqldump --defaults-extra-file="$defaults" --single-transaction --quick \
       --routines --events --triggers "$DB_NAME" 2>/dev/null | gzip -6 > "$target"; then
    rm -f "$defaults"
    [ -s "$target" ] || return 1
    return 0
  fi
  rm -f "$defaults" "$target"
  return 1
}

# Putting a dump back. db-tools reads every archive it writes, including the
# encrypted ones. Without it, a plain dump still goes back in with the mysql
# client alone, which is what makes a tree with a broken vendor/ recoverable.
restore_dump() {
  local dump=$1

  if [ -f "${APP_PATH}/vendor/bin/db-tools" ]; then
    local args=(restore "$dump" --force)
    [ -n "$BACKUP_ENCRYPT_PASSWORD" ] && args+=(--encryption-password="$BACKUP_ENCRYPT_PASSWORD")
    ( cd "$APP_PATH" && "$PHP_BIN" vendor/bin/db-tools "${args[@]}" )
    return $?
  fi

  case "$dump" in
    *.gpg|*.zst|*.zip)
      print error "vendor/bin/db-tools is missing, and $(basename "$dump") is in a format only db-tools reads."
      print info  "Run composer install in ${APP_PATH}, then try the restore again."
      return 1
      ;;
  esac
  command -v mysql >/dev/null 2>&1 || { print error "Neither db-tools nor the mysql client is installed."; return 1; }

  print warning "vendor/bin/db-tools is missing. Restoring with the mysql client."
  local defaults rc=0
  defaults="$(mysql_defaults_file)"
  mysql --defaults-extra-file="$defaults" -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`" 2>/dev/null || true
  case "$dump" in
    *.gz) gzip -dc "$dump" | mysql --defaults-extra-file="$defaults" "$DB_NAME" || rc=$? ;;
    *)    mysql --defaults-extra-file="$defaults" "$DB_NAME" < "$dump" || rc=$? ;;
  esac
  rm -f "$defaults"
  return "$rc"
}

# A restore tool reporting success is not evidence that the data is there. An
# empty database after a restore is the failure worth catching, because the
# operator walks away from it believing the dashboard is back.
verify_database_restored() {
  command -v mysql >/dev/null 2>&1 || return 0
  local defaults tables
  defaults="$(mysql_defaults_file)"
  tables="$(mysql --defaults-extra-file="$defaults" -N -B \
    -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}'" 2>/dev/null || echo 0)"
  rm -f "$defaults"
  [ "${tables:-0}" -gt 0 ] || return 1
  [ "$tables" -eq 1 ] && print success "The database ${DB_NAME} holds 1 table" \
                      || print success "The database ${DB_NAME} holds ${tables} tables"
}

if [ "$ACTION" = "restore" ]; then
  restore_from "$RESTORE_WHICH"
  exit 0
fi

# --- checks -------------------------------------------------------------------

[ "$ACTION" = "test" ] \
  && print info "Checking the backup of ${INSTANCE_NAME}. Nothing will be changed." \
  || print info "Starting backup of ${INSTANCE_NAME}"
print info "From: ${APP_PATH} (database ${DB_NAME})"
print info "To  : ${DEST_DIR}/"

ensure_destination_available
check_folder_ownership

# Free space where the dump is written. The last dump's size is the only honest
# estimate of the next one's.
NEEDED_MB=$(( (LAST_DUMP_BYTES / 1048576) * 3 ))
[ "$NEEDED_MB" -lt 1024 ] && NEEDED_MB=1024
mkdir -p "$STAGING_DIR"
STAGING_FREE_MB=$(df -Pk "$STAGING_DIR" | awk 'NR==2{print int($4/1024)}')
if [ "${STAGING_FREE_MB:-0}" -lt "$NEEDED_MB" ]; then
  fail "Only ${STAGING_FREE_MB} MB free on this machine, and the dump needs about ${NEEDED_MB} MB. Free up space and run the backup again."
fi

DEST_FREE_MB=$(dest_exec "df -Pk ${Q_DEST} 2>/dev/null | awk 'NR==2{print int(\$4/1024)}'" || echo 0)
DEST_FREE_MB=${DEST_FREE_MB:-0}
if [ "$DEST_FREE_MB" -lt "$NEEDED_MB" ]; then
  fail "Only ${DEST_FREE_MB} MB free where the backup is stored, and this run needs about ${NEEDED_MB} MB. Free up space or lower RETENTION in ${CONF_FILE}."
fi

# --- dry run ------------------------------------------------------------------

if [ "$ACTION" = "test" ]; then
  if command -v mysql >/dev/null 2>&1; then
    defaults="$(mysql_defaults_file)"
    if mysql --defaults-extra-file="$defaults" -e "SELECT 1" "$DB_NAME" >/dev/null 2>&1; then
      print success "The database ${DB_NAME} answers"
    else
      print warning "Could not connect to the database ${DB_NAME} with the credentials in config/autoload."
    fi
    rm -f "$defaults"
  fi
  [ -f "${APP_PATH}/vendor/bin/db-tools" ] \
    && print success "db-tools is present, so dumps restore with: php vendor/bin/db-tools restore FILE" \
    || print warning "vendor/bin/db-tools is missing. Dumps fall back to mysqldump and .sql.gz."
  print success "The destination answers and has ${DEST_FREE_MB} MB free"
  held="$(list_runs | wc -l | tr -d ' ')"
  print info "${held} backup(s) held there now, keeping the last ${RETENTION}"
  exit 0
fi

# --- the backup ---------------------------------------------------------------

SECONDS=0
STAMP="$(date -u +%Y%m%d-%H%M%S)"

# A run started in the same second as the one before it would land in the same
# folder and overwrite it. Wait for the next second instead, so every backup
# keeps its own folder and the count in --list means what it says.
tries=0
while [ "$tries" -lt 5 ] && dest_exec "test -d $(printf '%q' "${DEST_DIR}/${STAMP}")" 2>/dev/null; do
  sleep 1
  STAMP="$(date -u +%Y%m%d-%H%M%S)"
  tries=$((tries + 1))
done

RUN_DIR="${STAGING_DIR}/${STAMP}"
rm -rf "$RUN_DIR"
mkdir -p "${RUN_DIR}/db" "${RUN_DIR}/config"
chmod 700 "$STAGING_DIR" "$RUN_DIR"

print info "Dumping the database..."
dump_database "${RUN_DIR}/db" || fail "The database dump failed. Nothing has been sent to the destination."

DUMP_FILE="$(find "${RUN_DIR}/db" -maxdepth 1 -type f ! -name '*.meta.json' | sort | tail -1)"
[ -n "$DUMP_FILE" ] && [ -s "$DUMP_FILE" ] || fail "The database dump produced no file. Nothing has been sent to the destination."
LAST_DUMP="$(basename "$DUMP_FILE")"
LAST_DUMP_BYTES="$(stat -c %s "$DUMP_FILE" 2>/dev/null || echo 0)"
print success "Database dumped: ${LAST_DUMP} ($(du -h "$DUMP_FILE" | cut -f1))"

print info "Copying the configuration..."
tar -czf "${RUN_DIR}/config/autoload.tar.gz" -C "$APP_PATH" config/autoload \
  || fail "Could not copy ${APP_PATH}/config/autoload."
crontab -l > "${RUN_DIR}/config/crontab-root.txt" 2>/dev/null || true

APP_VERSION="unknown"
[ -f "${APP_PATH}/VERSION.txt" ] && APP_VERSION="$(head -1 "${APP_PATH}/VERSION.txt" | tr -d '\r\n')"
if [ "$APP_VERSION" = "unknown" ] && [ -f "${APP_PATH}/composer.json" ]; then
  APP_VERSION="$(awk -F'"' '/"version"/{print $4; exit}' "${APP_PATH}/composer.json")"
fi

cat > "${RUN_DIR}/manifest.txt" <<MANIFEST
instance=${INSTANCE_NAME}
instance_uuid=${INSTANCE_UUID}
hostname=$(hostname -f 2>/dev/null || hostname)
app_path=${APP_PATH}
app_version=${APP_VERSION}
php_version=$("$PHP_BIN" -r 'echo PHP_VERSION;')
database=${DB_NAME}
database_host=${DB_HOST}
dump_file=${LAST_DUMP}
dump_bytes=${LAST_DUMP_BYTES}
taken_at=$(date -u +%FT%TZ)
MANIFEST

if command -v sha256sum >/dev/null 2>&1; then
  ( cd "$RUN_DIR" && find . -type f ! -name SHA256SUMS -print0 | sort -z | xargs -0 sha256sum > SHA256SUMS )
fi

print info "Sending it to the destination..."
Q_RUN="$(printf '%q' "${DEST_DIR}/${STAMP}")"
dest_exec "mkdir -p ${Q_RUN}" || fail "Could not create ${DEST_DIR}/${STAMP} at the destination."
rsync "${RSYNC_OPTS[@]}" "${RUN_DIR}/" "$(rsync_target_for "${DEST_DIR}/${STAMP}/")" >/dev/null \
  || fail "The copy did not finish. See ${LOGFILE} for the details."
print success "Sent"

# Verification. A backup nobody checked is a backup nobody has.
if [ "$VERIFY_CHECKSUMS" = "yes" ] && [ -f "${RUN_DIR}/SHA256SUMS" ]; then
  if dest_exec "command -v sha256sum >/dev/null 2>&1"; then
    dest_exec "cd ${Q_RUN} && sha256sum -c --quiet SHA256SUMS" \
      || fail "What arrived at the destination does not match what was sent. The folder ${STAMP} is damaged."
    print success "Verified: every file at the destination matches its checksum"
  else
    REMAINING=$(rsync "${RSYNC_OPTS[@]}" --dry-run --itemize-changes "${RUN_DIR}/" "$(rsync_target_for "${DEST_DIR}/${STAMP}/")" | grep -c '^[<>]f' || true)
    [ "${REMAINING:-0}" -eq 0 ] || fail "${REMAINING} file(s) did not arrive intact at the destination."
    print success "Verified: every file arrived"
  fi
fi

# --- pruning ------------------------------------------------------------------
# Only ever after a verified copy. Deleting history to make room for a backup
# that then fails is how both copies are lost.

TOTAL=$(list_runs | wc -l | tr -d ' ')
if [ "${TOTAL:-0}" -gt "$RETENTION" ]; then
  DROP=$(( TOTAL - RETENTION ))
  while read -r old; do
    [ -n "$old" ] || continue
    dest_exec "rm -rf $(printf '%q' "${DEST_DIR}/${old}")" || print warning "Could not remove the old backup ${old}."
    print info "Removed the old backup ${old}"
  done < <(list_runs | head -n "$DROP")
fi

# Keep a couple of runs on this machine too, so a restore does not need the
# network for the most recent one.
find "$STAGING_DIR" -maxdepth 1 -mindepth 1 -type d -name '20*' | sort | head -n -"$LOCAL_KEEP" | while read -r old; do
  rm -rf "$old"
done

LAST_RUN_FOLDER="$STAMP"
BACKUP_SIZE=$(dest_exec "du -sh ${Q_DEST} 2>/dev/null | cut -f1" || echo "unknown")
BACKUP_SIZE=${BACKUP_SIZE:-unknown}

write_status ok "" "$BACKUP_SIZE" "$SECONDS"
print success "Backup finished in ${SECONDS}s. ${DEST_DIR} now holds $(list_runs | wc -l | tr -d ' ') backup(s), ${BACKUP_SIZE} in total."
RUNNER_SCRIPT

chmod 0755 "$RUNNER"
print success "Backup runner installed at $RUNNER"

# --- log rotation -------------------------------------------------------------

mkdir -p /etc/logrotate.d
cat > /etc/logrotate.d/smart-connect-backup <<'LOGROTATE'
/var/log/smart-connect-backup.log {
    weekly
    rotate 8
    missingok
    notifempty
    compress
    delaycompress
    copytruncate
}
LOGROTATE
print success "Log rotation configured"

# --- schedule -----------------------------------------------------------------

print header "Scheduling"
( crontab -l 2>/dev/null | grep -v "smart-connect-backup.sh" || true ) | crontab -
( crontab -l 2>/dev/null; echo "${SCHEDULE_CRON} ${RUNNER} >/dev/null 2>&1" ) | crontab -
print success "Backups will run ${SCHEDULE_TEXT}"

# --- recovering a rebuilt server ----------------------------------------------
# Backing up first would store this server's empty database over a retention
# slot. What a rebuilt server needs is the traffic in the other direction.

if [ "$ADOPTED" -eq 1 ]; then
  held="$(dest_exec "ls -1 ${q_dest} 2>/dev/null | grep -E '^[0-9]{8}-[0-9]{6}$' | wc -l" | tr -d '\r\n ')"
  if [ "${held:-0}" -gt 0 ]; then
    print header "This installation has ${held} backup(s) stored"
    print info "This server was set up as a rebuild of ${DEST_FOLDER}."
    echo
    if confirm "Restore the newest backup into ${APP_PATH} now?"; then
      # --yes, because the question has just been asked here.
      if "$RUNNER" --restore latest --yes; then
        print success "Restored. The scheduled backups start from this data."
      else
        print error "The restore did not finish. The backup settings are saved, so you can"
        print info  "fix the problem and run: sudo ${RUNNER} --restore latest"
        exit 1
      fi
    else
      print warning "Skipping the restore. The next backup stores this server's database as it is now."
      print info    "Restore later with: sudo ${RUNNER} --restore latest"
    fi
  fi
fi

# --- first backup, in the foreground -----------------------------------------
# The operator must not walk away believing this worked when it did not.

print header "Running the first backup now"
print info "This can take a while on a large database. Leave this window open."
echo

if "$RUNNER"; then
  echo
  print header "All done"
  print success "Backups are set up and the first one completed."
else
  echo
  print header "Setup finished, but the first backup failed"
  print error "The settings have been saved, but the first backup did not complete."
  print info  "Read the messages above, fix the problem, then run: sudo ${RUNNER}"
  exit 1
fi

# --- summary ------------------------------------------------------------------

echo
print info "Installation   : ${SANITIZED_NAME}"
print info "Backup folder  : ${DEST_FOLDER}  (unique to this installation)"
case "$DEST_MODE" in
  ssh)   print info "Destination    : ${SSH_USER}@${SSH_HOST}:${DEST_DIR}" ;;
  smb)   print info "Destination    : //${SMB_HOST}/${SMB_SHARE} -> ${DEST_DIR}" ;;
  local) print info "Destination    : ${DEST_DIR}" ;;
esac
print info "Schedule       : ${SCHEDULE_TEXT}, keeping the last ${RETENTION}"
echo
print info "Check it is working : sudo ${RUNNER} --status"
print info "Test the connection : sudo ${RUNNER} --test"
print info "Back up right now   : sudo ${RUNNER}"
print info "See what is stored  : sudo ${RUNNER} --list"
print info "Restore the newest  : sudo ${RUNNER} --restore latest"
print info "Stop the backups    : sudo ${RUNNER} --disable"

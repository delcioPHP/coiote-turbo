#!/bin/bash

# Configure Coiote Turbo if not already configured
COIOTE_CONFIG="/var/www/config/coioteTurbo.php"

if [ ! -f "$COIOTE_CONFIG" ]; then
    echo "Configuring Coiote Turbo..."
    php artisan vendor:publish --provider="Cabanga\CoioteTurbo\CoioteTurboServiceProvider" --tag="config"

    if [ $? -eq 0 ]; then
        echo "Coiote Turbo configured successfully."
    else
        echo "Failed to configure Coiote Turbo."
        exit 1
    fi
else
    echo "Coiote Turbo already configured."
fi

CONF_FILE="/var/www/deploy/server.conf"

if [ ! -f "$CONF_FILE" ]; then
  echo "Configuration file $CONF_FILE not found."
  exit 1
fi

echo "Loading server configuration from $CONF_FILE..."

declare -A COMMANDS

# Parse config file lines as key=value
while IFS='=' read -r key value || [ -n "$key" ]; do
    # Remove comments and trim all whitespace (including invisible chars)
    key=$(echo "$key" | sed 's/#.*//' | tr -d '[:space:]')
    value=$(echo "$value" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')

    # Skip empty lines
    if [[ -z "$key" ]]; then
        continue
    fi

    # Check if line starts with "command:"
    if [[ "$key" =~ ^command:(.+)$ ]]; then
        COMMAND_KEY="${BASH_REMATCH[1]}"
        COMMANDS[$COMMAND_KEY]="$value"
    fi
done < "$CONF_FILE"

# Execute non-blocking commands (e.g., queue, schedule) in background
for cmd in "${!COMMANDS[@]}"; do
    if [ "$cmd" != "start" ]; then
        echo "Starting background command: ${COMMANDS[$cmd]}"
        bash -c "${COMMANDS[$cmd]}" &
    fi
done

# Execute the main server command (blocking)
if [[ -n "${COMMANDS[start]}" ]]; then
    echo "Starting main server: ${COMMANDS[start]}"
    exec ${COMMANDS[start]}
else
    echo "No start command defined. Aborting..."
    exit 1
fi
#!/bin/bash

CONF_FILE="/var/www/deploy/server.conf"

if [ ! -f "$CONF_FILE" ]; then
  echo "Configuration file $CONF_FILE not found."
  exit 1
fi

echo "Loading server configuration from $CONF_FILE..."

declare -A COMMANDS

# Parse config file lines as key=value
while IFS='=' read -r key value || [ -n "$key" ]; do
    key=$(echo "$key" | sed 's/#.*//' | xargs)
    value=$(echo "$value" | xargs)

    # Skip empty or invalid lines
    if [[ -z "$key" || "$key" != command:* ]]; then
        continue
    fi

    COMMAND_KEY="${key#command:}"
    COMMANDS[$COMMAND_KEY]="$value"
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

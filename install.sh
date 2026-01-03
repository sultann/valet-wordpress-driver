#!/usr/bin/env bash
# ------------------------------------------------------------------------------
# Valet WordPress Driver Installer
# ------------------------------------------------------------------------------

set -e

DRIVER_URL="https://raw.githubusercontent.com/sultann/valet-wordpress-driver/master/WordPressValetDriver.php"
DRIVER_DIR="$HOME/.config/valet/Drivers"
DRIVER_FILE="$DRIVER_DIR/WordPressValetDriver.php"

echo "Installing Valet WordPress Driver..."

if [[ ! -d "$DRIVER_DIR" ]]; then
    echo "Error: Valet drivers directory not found. Is Valet installed?"
    exit 1
fi

curl -sL "$DRIVER_URL" -o "$DRIVER_FILE"

if [[ -f "$DRIVER_FILE" ]]; then
    echo "Driver installed to $DRIVER_FILE"

    if command -v valet >/dev/null 2>&1; then
        valet restart >/dev/null 2>&1
        echo "Valet restarted"
    fi

    echo "Done!"
else
    echo "Error: Failed to download driver"
    exit 1
fi
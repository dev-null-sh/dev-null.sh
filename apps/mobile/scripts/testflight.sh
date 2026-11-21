#!/usr/bin/env bash
#
# Builds the app against https://dev-null.sh and uploads it to App Store
# Connect, for TestFlight (ios/ExportOptions.plist).
#
# Signs for the team in DEVELOPMENT_TEAM: Xcode must be signed in to an
# Apple ID of that team (Xcode, Settings, Accounts), which may need the
# cloud-managed distribution certificate permission. xcodebuild reads that
# account from the login keychain, so it fails ("No Accounts") from a
# sandboxed shell, such as an agent's: run it from a regular terminal. Or with an App Store
# Connect API key of that team instead (Admin or App Manager role):
#
#   DEVELOPMENT_TEAM=… ASC_KEY_ID=… ASC_ISSUER_ID=… ./scripts/testflight.sh
#
# reads ~/.appstoreconnect/private_keys/AuthKey_$ASC_KEY_ID.p8, or ASC_KEY_PATH.
# The build number is the date, so each upload is new.
#
#   DEVELOPMENT_TEAM=… ./scripts/testflight.sh            archive and upload
#   DEVELOPMENT_TEAM=… ./scripts/testflight.sh --archive  archive only, to check the release build
set -euo pipefail

cd "$(dirname "$0")/.."

: "${DEVELOPMENT_TEAM:?DEVELOPMENT_TEAM is needed: the Apple team that publishes the app}"

ARCHIVE=build/DevNull.xcarchive
BUILD_NUMBER="${BUILD_NUMBER:-$(date +%Y%m%d%H%M)}"

# The release build loads the production site, never a local one
unset CAP_SERVER_URL
bunx cap sync ios

if ! grep -q '"url": "https://dev-null.sh/"' ios/App/App/capacitor.config.json; then
    echo "The app does not point to https://dev-null.sh/, see ios/App/App/capacitor.config.json" >&2
    exit 1
fi

AUTH=(-allowProvisioningUpdates)

if [ -n "${ASC_KEY_ID:-}" ]; then
    AUTH+=(
        -authenticationKeyID "$ASC_KEY_ID"
        -authenticationKeyIssuerID "${ASC_ISSUER_ID:?ASC_ISSUER_ID is needed with ASC_KEY_ID}"
        -authenticationKeyPath "${ASC_KEY_PATH:-$HOME/.appstoreconnect/private_keys/AuthKey_$ASC_KEY_ID.p8}"
    )
fi

rm -rf "$ARCHIVE" build/export

xcodebuild archive \
    -project ios/App/App.xcodeproj \
    -scheme App \
    -configuration Release \
    -destination 'generic/platform=iOS' \
    -archivePath "$ARCHIVE" \
    "${AUTH[@]}" \
    DEVELOPMENT_TEAM="$DEVELOPMENT_TEAM" \
    CURRENT_PROJECT_VERSION="$BUILD_NUMBER" \
    2>&1 | grep -E -i '(error:|warning: .*sign|ARCHIVE (SUCCEEDED|FAILED))' || true

if [ ! -d "$ARCHIVE" ]; then
    echo "The archive failed, see the errors above" >&2
    exit 1
fi

if [ "${1:-}" = "--archive" ]; then
    echo "Archived build $BUILD_NUMBER in $ARCHIVE (not uploaded)"
    exit 0
fi

xcodebuild -exportArchive \
    -archivePath "$ARCHIVE" \
    -exportOptionsPlist ios/ExportOptions.plist \
    -exportPath build/export \
    "${AUTH[@]}"

echo "Uploaded build $BUILD_NUMBER: it shows in App Store Connect, TestFlight tab, once processed (10 to 30 minutes)"

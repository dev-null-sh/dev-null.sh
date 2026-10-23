#!/usr/bin/env bash
# Installs static FFmpeg and FFprobe into bin/ for video processing. Laravel Cloud has no system packages,
# so its build command runs this before `composer install`.
#
# FFMPEG_URL     a .tar.xz of a static build for this CPU (Laravel Cloud runs on arm64)
# FFMPEG_SHA256  the archive's SHA-256; any other file fails the build
set -euo pipefail

: "${FFMPEG_URL:?Set FFMPEG_URL to the static FFmpeg archive.}"
: "${FFMPEG_SHA256:?Set FFMPEG_SHA256 to the archive checksum.}"

bin="$(cd "$(dirname "$0")/.." && pwd)/bin"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

curl --fail --silent --show-error --location --retry 3 --output "$work/ffmpeg.tar.xz" "$FFMPEG_URL"
echo "$FFMPEG_SHA256  $work/ffmpeg.tar.xz" | sha256sum --check --status \
    || { echo "FFmpeg archive checksum does not match FFMPEG_SHA256." >&2; exit 1; }

tar -xJf "$work/ffmpeg.tar.xz" -C "$work"
mkdir -p "$bin"

for tool in ffmpeg ffprobe; do
    found="$(find "$work" -type f -name "$tool" | head -n 1)"
    [ -n "$found" ] || { echo "The archive contains no $tool binary." >&2; exit 1; }
    install -m 0755 "$found" "$bin/$tool"
done

"$bin/ffmpeg" -hide_banner -version | head -n 1
"$bin/ffprobe" -hide_banner -version | head -n 1

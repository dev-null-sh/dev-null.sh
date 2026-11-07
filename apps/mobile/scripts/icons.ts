import sharp from 'sharp';

// The App Store refuses an icon with an alpha channel
for (const [source, icon] of [
    ['icon', 'AppIcon'],
    ['icon-dark', 'AppIcon-dark'],
]) {
    await sharp(`assets/${source}.svg`)
        .removeAlpha()
        .png()
        .toFile(`ios/App/App/Assets.xcassets/AppIcon.appiconset/${icon}.png`);
}

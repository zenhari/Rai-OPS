# PWA Setup Guide for Crew Plan

This guide explains how to set up and generate the required icons for the Crew Plan Progressive Web App (PWA).

## Overview

The Crew Plan PWA provides:
- ✅ Offline functionality
- ✅ App-like experience on Android, iOS, and Desktop
- ✅ Install prompt for users
- ✅ Service Worker for caching
- ✅ Responsive design for all devices

## Required Files

### 1. Icons (Required)

You need to create icons in the following sizes and place them in `crewplan/icons/` directory:

- `icon-72x72.png` - 72x72 pixels
- `icon-96x96.png` - 96x96 pixels
- `icon-128x128.png` - 128x128 pixels
- `icon-144x144.png` - 144x144 pixels
- `icon-152x152.png` - 152x152 pixels
- `icon-192x192.png` - 192x192 pixels (Required minimum)
- `icon-384x384.png` - 384x384 pixels
- `icon-512x512.png` - 512x512 pixels (Required minimum)

### 2. How to Generate Icons

#### Option 1: Using Online Tools

1. **PWA Asset Generator** (Recommended)
   - Visit: https://www.pwabuilder.com/imageGenerator
   - Upload a 512x512 PNG image (your app logo)
   - Download the generated icons
   - Extract to `crewplan/icons/` directory

2. **RealFaviconGenerator**
   - Visit: https://realfavicongenerator.net/
   - Upload your logo
   - Configure settings
   - Download and extract icons

#### Option 2: Using ImageMagick (Command Line)

If you have a 512x512 source image (`logo.png`), run:

```bash
# Create icons directory
mkdir -p crewplan/icons

# Generate all icon sizes
convert logo.png -resize 72x72 crewplan/icons/icon-72x72.png
convert logo.png -resize 96x96 crewplan/icons/icon-96x96.png
convert logo.png -resize 128x128 crewplan/icons/icon-128x128.png
convert logo.png -resize 144x144 crewplan/icons/icon-144x144.png
convert logo.png -resize 152x152 crewplan/icons/icon-152x152.png
convert logo.png -resize 192x192 crewplan/icons/icon-192x192.png
convert logo.png -resize 384x384 crewplan/icons/icon-384x384.png
convert logo.png -resize 512x512 crewplan/icons/icon-512x512.png
```

#### Option 3: Using GIMP/Photoshop

1. Open your logo in GIMP or Photoshop
2. Resize to each required size (72x72, 96x96, etc.)
3. Export as PNG
4. Save to `crewplan/icons/` directory

### 3. Icon Design Guidelines

- **Format**: PNG with transparency
- **Shape**: Square (1:1 aspect ratio)
- **Design**: Should work well on both light and dark backgrounds
- **Content**: Include calendar/airplane icon or Raimon Airways logo
- **Colors**: Use green (#16a34a) as primary color to match the app theme

## Testing PWA

### 1. Chrome DevTools

1. Open Chrome DevTools (F12)
2. Go to **Application** tab
3. Check:
   - **Manifest** - Should show all app details
   - **Service Workers** - Should show registered service worker
   - **Storage** - Check cache contents

### 2. Lighthouse

1. Open Chrome DevTools
2. Go to **Lighthouse** tab
3. Select **Progressive Web App** checkbox
4. Click **Generate report**
5. Should score 90+ for PWA

### 3. Mobile Testing

#### Android
1. Open Chrome on Android
2. Visit the dashboard page
3. Tap menu (3 dots)
4. Select "Add to Home screen" or "Install app"

#### iOS Safari
1. Open Safari on iPhone/iPad
2. Visit the dashboard page
3. Tap Share button
4. Select "Add to Home Screen"

#### Desktop
- **Chrome/Edge**: Look for install icon in address bar
- **Firefox**: Not supported (use Chrome/Edge)

## Features Implemented

### ✅ Service Worker
- Caches static assets
- Provides offline functionality
- Network-first strategy for API calls
- Cache-first strategy for static assets

### ✅ Install Prompt
- Custom install banner
- Header install button
- Automatic detection of installed state

### ✅ Manifest
- Complete app metadata
- Icon definitions
- Theme colors
- Display modes

### ✅ Cross-Platform Support
- Android (Chrome, Samsung Internet)
- iOS (Safari with Add to Home Screen)
- Desktop (Chrome, Edge)

## Troubleshooting

### Icons Not Showing
- Check file paths in `manifest.json`
- Ensure icons are in `crewplan/icons/` directory
- Verify icon files exist and are valid PNGs

### Service Worker Not Registering
- Check browser console for errors
- Ensure HTTPS (or localhost)
- Verify `service-worker.js` file exists
- Check file permissions

### Install Prompt Not Showing
- App must meet PWA criteria:
  - HTTPS (or localhost)
  - Valid manifest.json
  - Registered service worker
  - At least 192x192 and 512x512 icons
- User must have visited site at least once
- Not already installed

### Offline Not Working
- Check service worker is active
- Verify assets are cached
- Check browser cache settings
- Test in Incognito mode

## Production Deployment

### 1. HTTPS Required
PWAs require HTTPS (except localhost). Ensure your production server has:
- Valid SSL certificate
- HTTPS enabled
- Proper redirect from HTTP to HTTPS

### 2. Update Service Worker Version
When updating the app, update `CACHE_NAME` in `service-worker.js`:
```javascript
const CACHE_NAME = 'crew-plan-v1.0.1'; // Increment version
```

### 3. Test All Features
- Install on Android device
- Install on iOS device
- Install on desktop
- Test offline functionality
- Verify all icons display correctly

## Support

For issues or questions:
1. Check browser console for errors
2. Verify all files are in correct locations
3. Test in different browsers
4. Check Lighthouse PWA score

---

**Created**: 2025-01-XX
**Version**: 1.0.0


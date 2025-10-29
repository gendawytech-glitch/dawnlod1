# TikTok Downloader - Deployment Guide

## Standalone PHP Backend System

This application has been converted from Laravel to a standalone PHP system that works on any shared hosting platform.

## System Requirements

- PHP 7.4 or higher
- cURL extension enabled
- mod_rewrite enabled (Apache)
- 128MB memory limit (recommended)

## File Structure

```
project/
├── api/
│   ├── .htaccess                    # Apache configuration
│   ├── fetch.php                    # Video fetch endpoint
│   ├── download.php                 # Download proxy endpoint
│   ├── StandaloneTikTok.php         # Core TikTok parser
│   └── StandaloneTikTokVideo.php    # Video data model
├── components/                      # React components
├── services/                        # Frontend services
├── dist/                            # Built frontend (after npm run build)
└── index.html                       # Main entry point
```

## Deployment Instructions

### Step 1: Build the Frontend

```bash
npm install
npm run build
```

### Step 2: Upload Files

Upload the following to your hosting:

- All files from `dist/` folder → Root directory
- `api/` folder → Root directory
- `index.html` → Root directory

### Step 3: Verify PHP Settings

Ensure your hosting has:
- PHP 7.4+ enabled
- cURL extension enabled
- `allow_url_fopen` enabled

### Step 4: Test the Application

1. Visit your domain
2. Paste a TikTok video URL
3. Click "Download"
4. Select download quality

## API Endpoints

### POST /api/fetch.php

Fetches TikTok video information.

**Request:**
```json
{
  "url": "https://www.tiktok.com/@username/video/1234567890"
}
```

**Response:**
```json
{
  "author": {
    "username": "username",
    "avatar": "https://..."
  },
  "mp3URL": "https://...",
  "coverURL": "https://...",
  "watermark": {
    "url": "https://...",
    "size": "12345678"
  },
  "downloadUrls": [
    {
      "url": "https://...",
      "isHD": true,
      "size": "12345678",
      "idx": 0
    }
  ],
  "caption": "Video caption"
}
```

### POST /api/download.php

Streams video download.

**Request (Form Data):**
- `url`: Base64 encoded video URL
- `extension`: mp4 or mp3
- `size`: File size (optional)

## How It Works

1. **No Laravel Dependencies**: Completely standalone PHP
2. **Direct TikTok Parsing**: Fetches video data directly from TikTok
3. **Stream Downloads**: Proxies downloads through your server
4. **Multiple Quality Options**: HD, Standard, with/without watermark
5. **MP3 Extraction**: Audio-only downloads

## Key Features

- Works on basic shared hosting (iPage, GoDaddy, Bluehost, etc.)
- No Composer required
- No SSH access needed
- No .env configuration
- Simple file upload deployment
- No database required

## Troubleshooting

### Videos Not Loading

1. Check PHP error log
2. Verify cURL is enabled: `php -m | grep curl`
3. Check `allow_url_fopen` is enabled
4. Increase PHP memory limit to 128MB

### Downloads Not Working

1. Verify `.htaccess` is uploaded to `/api/` folder
2. Check mod_rewrite is enabled
3. Ensure PHP execution time is at least 600 seconds

### CORS Errors

The API endpoints include CORS headers by default. If issues persist:
1. Check Apache/nginx allows header modifications
2. Verify `.htaccess` is being processed

## Security Notes

- The system uses cURL with SSL verification disabled for compatibility
- No user data is stored
- All downloads are streamed through the server
- Rate limiting should be implemented at server level

## Support

For issues or questions, check:
1. PHP error logs
2. Browser console for frontend errors
3. Network tab for API request/response details

## License

This standalone version maintains the same functionality as the original Laravel application while being compatible with standard shared hosting environments.

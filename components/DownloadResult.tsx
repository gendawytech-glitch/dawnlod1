import React from 'react';
import { TikTokVideoData, downloadVideo } from '../services/tiktokService';

interface DownloadResultProps {
  data: TikTokVideoData;
}

export default function DownloadResult({ data }: DownloadResultProps) {
  const handleDownload = (url: string, isMP3: boolean = false, size?: string) => {
    const extension = isMP3 ? 'mp3' : 'mp4';
    downloadVideo(url, extension, size);
  };

  return (
    <div className="download-result">
      <div className="video-info">
        <div className="author-info">
          {data.author.avatar && (
            <img
              src={data.author.avatar}
              alt={data.author.username}
              className="author-avatar"
            />
          )}
          <span className="author-username">@{data.author.username}</span>
        </div>
        {data.caption && (
          <p className="video-caption">{data.caption}</p>
        )}
      </div>

      <div className="download-options">
        <h3>Download Options</h3>

        {data.downloadUrls && data.downloadUrls.length > 0 && (
          <div className="download-section">
            <h4>Video Quality</h4>
            {data.downloadUrls.map((download, index) => (
              <button
                key={index}
                onClick={() => handleDownload(download.url, false, download.size)}
                className={`download-button ${download.isHD ? 'hd' : ''}`}
              >
                {download.isHD ? 'HD Quality' : 'Standard Quality'}
                {download.size && ` (${formatSize(download.size)})`}
              </button>
            ))}
          </div>
        )}

        {data.watermark && data.watermark.url && (
          <div className="download-section">
            <h4>With Watermark</h4>
            <button
              onClick={() => handleDownload(data.watermark.url, false, data.watermark.size)}
              className="download-button"
            >
              Download with Watermark
              {data.watermark.size && ` (${formatSize(data.watermark.size)})`}
            </button>
          </div>
        )}

        {data.mp3URL && (
          <div className="download-section">
            <h4>Audio Only</h4>
            <button
              onClick={() => handleDownload(data.mp3URL, true)}
              className="download-button mp3"
            >
              Download MP3
            </button>
          </div>
        )}
      </div>
    </div>
  );
}

function formatSize(size: string | number): string {
  const bytes = typeof size === 'string' ? parseInt(size) : size;
  if (isNaN(bytes)) return '';

  const mb = bytes / (1024 * 1024);
  return mb.toFixed(2) + ' MB';
}

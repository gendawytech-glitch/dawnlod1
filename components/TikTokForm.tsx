import React, { useState } from 'react';
import { fetchTikTokVideo, TikTokVideoData } from '../services/tiktokService';

interface TikTokFormProps {
  onResult: (data: TikTokVideoData) => void;
}

export default function TikTokForm({ onResult }: TikTokFormProps) {
  const [url, setUrl] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!url.trim()) {
      setError('Please enter a TikTok URL');
      return;
    }

    setLoading(true);
    setError('');

    try {
      const data = await fetchTikTokVideo(url);
      onResult(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch video');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="tiktok-form-container">
      <form onSubmit={handleSubmit} className="tiktok-form">
        <div className="input-group">
          <input
            type="text"
            value={url}
            onChange={(e) => setUrl(e.target.value)}
            placeholder="Paste TikTok video URL here..."
            className="url-input"
            disabled={loading}
          />
          <button
            type="submit"
            className="submit-button"
            disabled={loading}
          >
            {loading ? 'Loading...' : 'Download'}
          </button>
        </div>
        {error && <div className="error-message">{error}</div>}
      </form>
    </div>
  );
}

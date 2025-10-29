import React, { useState } from 'react';
import TikTokForm from './components/TikTokForm';
import DownloadResult from './components/DownloadResult';
import { TikTokVideoData } from './services/tiktokService';
import './App.css';

export default function App() {
  const [videoData, setVideoData] = useState<TikTokVideoData | null>(null);

  return (
    <div className="app">
      <header className="app-header">
        <h1>TikTok Video Downloader</h1>
        <p>Download TikTok videos without watermark</p>
      </header>

      <main className="app-main">
        <TikTokForm onResult={setVideoData} />
        {videoData && <DownloadResult data={videoData} />}
      </main>

      <footer className="app-footer">
        <p>Fast and free TikTok video downloader</p>
      </footer>
    </div>
  );
}

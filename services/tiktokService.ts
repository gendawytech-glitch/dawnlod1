export interface TikTokVideoData {
  author: {
    username: string;
    avatar: string;
  };
  mp3URL: string;
  coverURL: string;
  watermark: {
    url: string;
    size?: string;
  };
  downloadUrls: Array<{
    url: string;
    isHD: boolean;
    size?: string;
    idx: number;
  }>;
  caption: string;
}

export const fetchTikTokVideo = async (url: string): Promise<TikTokVideoData> => {
  const response = await fetch('/api/fetch.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ url }),
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.message || 'Failed to fetch video');
  }

  return response.json();
};

export const downloadVideo = (url: string, extension: string = 'mp4', size?: string) => {
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = '/api/download.php';
  form.target = '_blank';

  const urlInput = document.createElement('input');
  urlInput.type = 'hidden';
  urlInput.name = 'url';
  urlInput.value = btoa(url);
  form.appendChild(urlInput);

  const extInput = document.createElement('input');
  extInput.type = 'hidden';
  extInput.name = 'extension';
  extInput.value = extension;
  form.appendChild(extInput);

  if (size) {
    const sizeInput = document.createElement('input');
    sizeInput.type = 'hidden';
    sizeInput.name = 'size';
    sizeInput.value = size;
    form.appendChild(sizeInput);
  }

  document.body.appendChild(form);
  form.submit();
  document.body.removeChild(form);
};

<div align="center">
<img width="1200" height="475" alt="GHBanner" src="https://ai.google.dev/static/site-assets/images/share-ais-513315318.png" />
</div>

# Run and deploy your AI Studio app

This contains everything you need to run your app locally.

View your app in AI Studio: https://ai.studio/apps/4e2ff5c6-1b24-4db7-b536-b7bd62030627

## Run Locally

**Prerequisites:**  Node.js


1. Install dependencies:
   `npm install`
2. Set the `GEMINI_API_KEY` in [.env.local](.env.local) to your Gemini API key
3. Run the app:
   `npm run dev`

## Local AI processing requirements
This project does not use fake transcript or score fallbacks. For real interview processing, install/configure the following on the machine running PHP:
- FFmpeg and FFprobe
- Local Whisper-compatible CLI (configure `WHISPER_BIN` and `WHISPER_MODEL`)
- Ollama plus the configured local model
- Piper plus a local `.onnx` voice model

Copy `.env.example` values into your server environment. The PHP code integrates these local tools; it cannot embed the binaries inside PHP itself.

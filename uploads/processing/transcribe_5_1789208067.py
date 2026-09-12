from faster_whisper import WhisperModel

audio_path = r"D:\\xampp\\htdocs\\ai_proj\\uploads\\processing\\session_5_1789208066.wav"

model = WhisperModel(
    "base",
    compute_type="int8"
)

segments, info = model.transcribe(audio_path)

for segment in segments:
    print(segment.text.strip())
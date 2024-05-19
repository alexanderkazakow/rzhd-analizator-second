import os
import sys

fileName = sys.argv[1]
filePath = os.getcwd() + "/../python/model"
print("Using file {}...".format(fileName))

print()

print("Converting to wav...")
cmd = "ffmpeg -y -i {} -acodec pcm_u8 -filter:a \"volume=15dB\" {}/noizy_wav/result.wav".format(fileName, filePath)
os.popen(cmd).read()
print("Converting done")

print()

print("Denoizing...")
cmd = "python {}/DTLN/run_evaluation.py -i {}/noizy_wav -o {}/denoized_wav -m {}/DTLN/pretrained_model/model.h5".format(filePath, filePath, filePath, filePath)
os.popen(cmd).read()
print("Denoizing done")

print()

print("Transcribing...")
cmd = "vosk-transcriber -i {}/denoized_wav/result.wav -o ./out.txt --model-name vosk-model-small-ru-0.22".format(filePath)
os.popen(cmd).read()
print("Transcribing done")

#!/bin/bash

set -e

echo "🔍 Определение платформы..."
OS=$(uname)

if [[ "$OS" == "Linux" ]]; then
  echo "🟢 Linux-система (Ubuntu/Debian)"

  echo "🔄 Обновляем apt..."
  sudo apt update

  echo "📦 Устанавливаем sox и поддержку всех форматов..."
  sudo apt install -y sox libsox-fmt-all

  echo "🧩 Устанавливаем PHP GD..."
  sudo apt install -y php-gd

elif [[ "$OS" == "Darwin" ]]; then
  echo "🍏 macOS с Homebrew"

  echo "📦 Устанавливаем sox..."
  brew install sox

  echo "🎵 Устанавливаем ffmpeg для поддержки кодеков (mp3, ogg, и т.д.)"
  brew install ffmpeg

  echo "✅ PHP и GD считаются уже установленными"
else
  echo "❌ Неизвестная ОС: $OS"
  exit 1
fi

echo "✅ Установка завершена."
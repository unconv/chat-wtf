FROM python:3.8-bookworm

WORKDIR /usr/src/app

COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

VOLUME /usr/src/app/data

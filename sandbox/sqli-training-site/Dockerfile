# Shared image for both the vulnerable and secure training apps.
# docker-compose.yml runs the same image twice with a different command.
FROM python:3.12-slim

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

# No CMD here on purpose -- docker-compose.yml supplies the command per
# service (init_db.py + app.py, or init_db.py + secure_app.py).

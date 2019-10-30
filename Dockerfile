FROM python:3.8.0-alpine3.10

WORKDIR /usr/local/app

COPY requirements.txt .

RUN pip install -r requirements.txt

COPY *.py ./

CMD ["python", "main.py"]

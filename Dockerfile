FROM python:3.7.1-alpine3.8

WORKDIR /usr/local/app

COPY requirements.txt .

RUN pip install -r requirements.txt

COPY *.py ./

CMD ["python", "main.py"]

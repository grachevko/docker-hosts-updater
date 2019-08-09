FROM python:3.7.4-alpine3.10

WORKDIR /usr/local/app

COPY requirements.txt .

RUN pip install -r requirements.txt

COPY *.py ./

CMD ["python", "main.py"]

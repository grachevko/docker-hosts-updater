FROM python:3.14.2-alpine3.23

WORKDIR /usr/local/app

COPY requirements.txt .

RUN pip install -r requirements.txt

COPY *.py ./

CMD ["python", "main.py"]

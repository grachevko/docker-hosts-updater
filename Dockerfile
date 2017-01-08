FROM golang:alpine

ENV GOPATH /go
ENV GOBIN=$GOPATH/bin\
    PATH=$PATH:$GOBIN

COPY main.go main.go

RUN apk add --no-cache git gcc g++ make --virtual .build-deps \
    && go get \
    && go build main.go \
    && cp main /usr/local/bin/grachev-dhu \
    && apk del .build-deps

ENTRYPOINT ["grachev-dhu"]

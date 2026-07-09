FROM golang:1.26-alpine AS build

WORKDIR /src
COPY go.mod ./
COPY main.go ./
RUN CGO_ENABLED=0 go build -trimpath -ldflags="-s -w" -o /out/system-updater .

FROM docker:29-cli

COPY --from=build /out/system-updater /usr/local/bin/system-updater

EXPOSE 8080
ENTRYPOINT ["system-updater"]

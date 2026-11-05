FROM postgres:18-alpine@sha256:10a5c69a83035ab5239f5620d708520e8b28f5aa1f2b20143e6024532edadfb5

RUN apk add --no-cache age bash curl rclone

COPY backup.sh restore.sh /usr/local/bin/

USER postgres
ENTRYPOINT ["backup.sh"]

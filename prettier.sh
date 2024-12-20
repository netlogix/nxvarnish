#!/bin/bash
CMD=$@

if [[ $# -eq 0 ]]; then
    echo "No arguments supplied using --check as default. If you want to format files use --write."
    CMD="--check"
fi

INTERACTIVE_MODE="-it"

if [[ -n "${CI}" ]]; then
    echo "Running on CI, disabling interactive mode."
    INTERACTIVE_MODE=""
fi

docker run --rm $INTERACTIVE_MODE -v $(pwd):/home/prettier/code ghcr.io/netlogix/docker/prettier:stable $CMD

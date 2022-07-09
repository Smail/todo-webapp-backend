#!/usr/bin/env sh

# Create keys diretory and generate keys
mkdir keys
# Generate keys
ssh-keygen -t rsa -P "" -b 4096 -m PEM -f keys/token_rs256
ssh-keygen -e -m PEM -f keys/token_rs256 > keys/token_rs256.pub

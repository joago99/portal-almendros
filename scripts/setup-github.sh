#!/usr/bin/env bash
set -e
cd /c/Users/joaqu/portal-almendros

if [[ -z "$(git config user.name)" || -z "$(git config user.email)" ]]; then
  echo "Define tu identidad de git y corroboro el repo para subirlo."
else
  echo "Identidad ok: $(git config user.name) <$(git config user.email)>"
fi

# 1) Repo local ya inicializado. Solo falta crear el remoto en GitHub y pushear.

#!/bin/bash -e

VERSION=1.0.1
SRC=src
DIST=soundexsearch-v${VERSION}.zip
if [ -f "$DIST" ] ; then
	rm "$DIST"
fi

zip -r "$DIST" "${SRC}" README.md gpl-3.0.md resources
echo Made $DIST

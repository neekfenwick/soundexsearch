#!/bin/bash -e

VERSION=1.0.0
DIST=soundexsearch-${VERSION}.zip
if [ -f "$DIST" ] ; then
	rm "$DIST"
fi

zip -r "$DIST" "${VERSION}" README.md gpl-3.0.md resources
echo Made $DIST
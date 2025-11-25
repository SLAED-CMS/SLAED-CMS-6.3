#!/bin/bash
# Generiert ein Changelog aus Git-Historie

echo "# SLAED CMS Changelog"
echo ""
echo "Alle Änderungen am SLAED CMS System:"
echo ""

git log --pretty=format:"%h - %ad - %s%n%b" --date=short --reverse

echo ""
echo "---"
echo "Generiert am: $(date '+%Y-%m-%d %H:%M:%S')"

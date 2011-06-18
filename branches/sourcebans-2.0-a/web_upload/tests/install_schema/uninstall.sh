#!/bin/sh
/usr/bin/mysql --user=sourcebans_test --password=sourcebans sourcebans_test < /home/sourcebans/buildbot/slave/full/build/tests/install_schema/uninstall.sql

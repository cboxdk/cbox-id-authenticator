Placeholder so this directory survives packaging.

The iOS/Android build zips the app with -x "*/.*", which drops every
dot-prefixed path — including .gitkeep. An empty directory is then omitted
from the archive entirely, and the bundled app boots without a view cache
path, failing package:discover with "Please provide a valid cache path".

# UPGRADE TO 5.1

## BC BREAK: redirectUris and allowedGrantTypes are now json and not array anymore

This change were made to be doctrine/dbal v4 compliant.
Make sure to migrate you database schema.
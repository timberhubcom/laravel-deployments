# Laravel Deployments

This a repository/package that can be used to deploy your Laravel application to a server.
The main and final outcome are commands with parameters on target servers and extra actions that need to take place.

## Usage

You can use it either by installing it to your Laravel project or in a GitHub Action.
(We will further update soon with a GitHub Action Marketplace link)

### Install to your Laravel project

Install the package with composer:
```shell
composer require timberhub/laravel-deployments
```

Use the command helper to adjust the parameters. e.g:
```shell
./vendor/bin/ci-actions branch:deploy:forge -h
```

### Deploy to a Forge Server

Use the following command to deploy your application to a Forge server like this:
```shell
./vendor/bin/ci-actions branch:deploy:forge \
  --token=[FORGE_API_TOKEN] \
  --server=[FORGE_SERVER_ID] \
  --branch=[BRANCH_NAME] \
  --repository=[REPOSITORY_URL] \
  --branch=[BRANCH_NAME] \
  --domain=[DOMAIN_NAME] \
  --db-name=[DB_NAME] \
  --db-user=[DB_USER] \
  --db-password=[DB_PASSWORD]
```
Then you can add extra parameters like the environment variables you want to set on the server, or commands that you want to run after the deployment. Those commands could be your own deployment script to finalize the installation of your application.

PS: Make sure your domain name points to the server you are deploying to before you run the command. The deployment URL will look like `https://[BRANCH_NAME].[REPOSITORY_NAME][DOMAIN_NAME].`.

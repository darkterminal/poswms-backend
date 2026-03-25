# CI/CD Pipeline Setup

## Overview

This document provides comprehensive instructions for setting up Continuous Integration and Continuous Deployment (CI/CD) pipelines for MSWMS. Automated pipelines ensure consistent, reliable, and repeatable deployments.

## CI/CD Platform Selection

### Supported Platforms

| Platform | Best For | Cost | Integration |
|----------|----------|------|-------------|
| GitHub Actions | GitHub repositories | Free for public, paid for private | Native GitHub |
| GitLab CI | GitLab repositories | Free tier available | Native GitLab |
| Jenkins | Self-hosted, custom | Free (self-hosted) | Universal |
| CircleCI | Cloud-native | Free tier available | GitHub, Bitbucket |
| Travis CI | Open source | Free for public | GitHub |

### Recommended: GitHub Actions

**Advantages:**
- Native GitHub integration
- Free for public repositories
- Generous free tier for private repos
- Extensive marketplace
- Easy configuration

## GitHub Actions Setup

### Repository Secrets

**Configure Secrets:**
```bash
# In GitHub Repository Settings > Secrets and variables > Actions

# Add these secrets:
DEPLOY_SSH_KEY          # SSH private key for deployment
AWS_ACCESS_KEY_ID       # AWS access key
AWS_SECRET_ACCESS_KEY   # AWS secret key
DATABASE_URL            # Production database URL
REDIS_URL               # Production Redis URL
APP_KEY                 # Laravel application key
APP_ENV                 # production
APP_DEBUG               # false
```

### Main Workflow Configuration

**Create Workflow File:**
```yaml
# .github/workflows/deploy.yml

name: Deploy to Production

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

env:
  APP_NAME: MSWMS
  APP_ENV: production
  APP_DEBUG: false
  APP_KEY: ${{ secrets.APP_KEY }}
  DB_CONNECTION: pgsql
  DB_HOST: ${{ secrets.DB_HOST }}
  DB_DATABASE: ${{ secrets.DB_DATABASE }}
  DB_USERNAME: ${{ secrets.DB_USERNAME }}
  DB_PASSWORD: ${{ secrets.DB_PASSWORD }}
  REDIS_HOST: ${{ secrets.REDIS_HOST }}
  REDIS_PASSWORD: ${{ secrets.REDIS_PASSWORD }}

jobs:
  # Job 1: Code Quality Checks
  code-quality:
    runs-on: ubuntu-latest
    name: Code Quality
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, xml, curl, pgsql, redis
          tools: composer:v2
          coverage: none

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress --no-suggest

      - name: Run Laravel Pint
        run: vendor/bin/pint --test

      - name: Run PHPStan
        run: vendor/bin/phpstan analyse --memory-limit=1G

  # Job 2: Testing
  tests:
    runs-on: ubuntu-latest
    name: Tests
    
    services:
      postgres:
        image: postgres:15
        env:
          POSTGRES_DB: testing
          POSTGRES_USER: postgres
          POSTGRES_PASSWORD: password
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
        ports:
          - 5432:5432

      redis:
        image: redis:7
        options: >-
          --health-cmd "redis-cli ping"
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
        ports:
          - 6379:6379

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, xml, curl, pgsql, redis
          tools: composer:v2
          coverage: none

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress --no-suggest

      - name: Generate application key
        run: php artisan key:generate

      - name: Run migrations
        run: php artisan migrate --force
        env:
          DB_CONNECTION: pgsql
          DB_HOST: localhost
          DB_PORT: 5432
          DB_DATABASE: testing
          DB_USERNAME: postgres
          DB_PASSWORD: password

      - name: Run tests
        run: php artisan test --compact
        env:
          DB_CONNECTION: pgsql
          DB_HOST: localhost
          DB_PORT: 5432
          DB_DATABASE: testing
          DB_USERNAME: postgres
          DB_PASSWORD: password
          REDIS_HOST: localhost
          REDIS_PORT: 6379

  # Job 3: Build Assets
  build:
    runs-on: ubuntu-latest
    name: Build Assets
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'

      - name: Install dependencies
        run: npm ci

      - name: Build assets
        run: npm run build

      - name: Upload build artifacts
        uses: actions/upload-artifact@v4
        with:
          name: build-assets
          path: public/build
          retention-days: 7

  # Job 4: Deploy to Staging
  deploy-staging:
    needs: [code-quality, tests, build]
    runs-on: ubuntu-latest
    name: Deploy to Staging
    if: github.ref == 'refs/heads/main'
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v4
        with:
          fetch-depth: 0

      - name: Download build artifacts
        uses: actions/download-artifact@v4
        with:
          name: build-assets
          path: public/build

      - name: Setup SSH
        uses: webfactory/ssh-agent@v0.7.0
        with:
          ssh-private-key: ${{ secrets.DEPLOY_SSH_KEY }}

      - name: Deploy to Staging
        run: |
          mkdir -p ~/.ssh
          echo "Host staging.mswms.example.com
              StrictHostKeyChecking no
              UserKnownHostsFile=/dev/null" > ~/.ssh/config
          
          ssh deploy@staging.mswms.example.com << 'ENDSSH'
            cd /var/www/mswms
            git pull origin staging
            composer install --no-dev --optimize-autoloader --no-interaction
            php artisan config:clear
            php artisan cache:clear
            php artisan route:clear
            php artisan view:clear
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            php artisan queue:restart
            chmod -R 775 storage bootstrap/cache
            chown -R www-data:www-data storage bootstrap/cache
          ENDSSH

      - name: Health Check
        run: |
          sleep 30
          curl -f https://staging.mswms.example.com/api/health || exit 1

  # Job 5: Deploy to Production
  deploy-production:
    needs: deploy-staging
    runs-on: ubuntu-latest
    name: Deploy to Production
    if: github.ref == 'refs/heads/main'
    environment: production
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v4
        with:
          fetch-depth: 0

      - name: Download build artifacts
        uses: actions/download-artifact@v4
        with:
          name: build-assets
          path: public/build

      - name: Setup SSH
        uses: webfactory/ssh-agent@v0.7.0
        with:
          ssh-private-key: ${{ secrets.DEPLOY_SSH_KEY }}

      - name: Enable Maintenance Mode
        run: |
          ssh deploy@api.mswms.example.com << 'ENDSSH'
            cd /var/www/mswms
            php artisan down --secret="maintenance_github_actions" --retry=60
          ENDSSH

      - name: Create Backup
        run: |
          ssh deploy@api.mswms.example.com << 'ENDSSH'
            cd /var/www/mswms
            pg_dump -h localhost -U mswms_prod_user mswms_production | gzip > /var/backups/mswms/pre_deploy_$(date +%Y%m%d_%H%M%S).sql.gz
          ENDSSH

      - name: Deploy to Production
        run: |
          ssh deploy@api.mswms.example.com << 'ENDSSH'
            cd /var/www/mswms
            git pull origin main
            composer install --no-dev --optimize-autoloader --no-interaction --classmap-authoritative
            npm install --production
            npm run build
            php artisan config:clear
            php artisan cache:clear
            php artisan route:clear
            php artisan view:clear
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            composer dump-autoload --classmap-authoritative
            php artisan queue:restart
            sudo systemctl restart php8.3-fpm
            sudo supervisorctl restart mswms-worker:*
            chmod -R 775 storage bootstrap/cache
            chown -R www-data:www-data storage bootstrap/cache
          ENDSSH

      - name: Wait for Deployment
        run: sleep 30

      - name: Health Check
        run: |
          HEALTH_STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://api.mswms.example.com/api/health)
          if [ "$HEALTH_STATUS" != "200" ]; then
            echo "Health check failed with status $HEALTH_STATUS"
            exit 1
          fi

      - name: Disable Maintenance Mode
        if: success()
        run: |
          ssh deploy@api.mswms.example.com << 'ENDSSH'
            cd /var/www/mswms
            php artisan up
          ENDSSH

      - name: Rollback on Failure
        if: failure()
        run: |
          ssh deploy@api.mswms.example.com << 'ENDSSH'
            cd /var/www/mswms
            php artisan migrate:rollback --force
            php artisan up
          ENDSSH
```

## Staging Workflow

**Create Staging Workflow:**
```yaml
# .github/workflows/deploy-staging.yml

name: Deploy to Staging

on:
  push:
    branches: [staging]

env:
  APP_NAME: MSWMS
  APP_ENV: staging
  APP_DEBUG: false
  APP_KEY: ${{ secrets.APP_KEY_STAGING }}

jobs:
  deploy-staging:
    runs-on: ubuntu-latest
    name: Deploy to Staging
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup SSH
        uses: webfactory/ssh-agent@v0.7.0
        with:
          ssh-private-key: ${{ secrets.DEPLOY_SSH_KEY_STAGING }}

      - name: Deploy
        run: |
          ssh deploy@staging.mswms.example.com << 'ENDSSH'
            cd /var/www/mswms
            git pull origin staging
            composer install --no-dev --optimize-autoloader --no-interaction
            php artisan config:clear
            php artisan cache:clear
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan queue:restart
          ENDSSH

      - name: Health Check
        run: |
          sleep 30
          curl -f https://staging.mswms.example.com/api/health
```

## GitLab CI Configuration

**Create .gitlab-ci.yml:**
```yaml
# .gitlab-ci.yml

stages:
  - test
  - build
  - deploy-staging
  - deploy-production

variables:
  APP_NAME: MSWMS
  APP_ENV: production
  APP_DEBUG: false
  PHP_VERSION: "8.3"

# Test Stage
test:
  stage: test
  image: php:8.3-cli
  services:
    - postgres:15
    - redis:7
  
  before_script:
    - docker-php-ext-install pdo_pgsql
    - curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    - composer install --prefer-dist --no-progress --no-suggest
  
  script:
    - php artisan key:generate
    - php artisan migrate --force
    - php artisan test --compact
  
  variables:
    DB_CONNECTION: pgsql
    DB_HOST: postgres
    DB_DATABASE: gitlab
    DB_USERNAME: postgres
    DB_PASSWORD: ""
    REDIS_HOST: redis

# Build Stage
build:
  stage: build
  image: node:20
  
  script:
    - npm ci
    - npm run build
  
  artifacts:
    paths:
      - public/build
    expire_in: 1 week

# Deploy Staging
deploy-staging:
  stage: deploy-staging
  image: alpine:latest
  environment: staging
  only:
    - staging
  
  before_script:
    - apk add --no-cache openssh-client
    - eval $(ssh-agent -s)
    - echo "$DEPLOY_SSH_KEY" | ssh-add -
    - mkdir -p ~/.ssh
    - chmod 700 ~/.ssh
  
  script:
    - ssh -o StrictHostKeyChecking=no deploy@staging.mswms.example.com << 'ENDSSH'
        cd /var/www/mswms
        git pull origin staging
        composer install --no-dev --optimize-autoloader --no-interaction
        php artisan migrate --force
        php artisan config:cache
        php artisan route:cache
        php artisan queue:restart
      ENDSSH

# Deploy Production
deploy-production:
  stage: deploy-production
  image: alpine:latest
  environment: production
  only:
    - main
  when: manual
  
  before_script:
    - apk add --no-cache openssh-client
    - eval $(ssh-agent -s)
    - echo "$DEPLOY_SSH_KEY" | ssh-add -
    - mkdir -p ~/.ssh
    - chmod 700 ~/.ssh
  
  script:
    - ssh -o StrictHostKeyChecking=no deploy@api.mswms.example.com << 'ENDSSH'
        cd /var/www/mswms
        php artisan down --secret="maintenance_gitlab" --retry=60
        git pull origin main
        composer install --no-dev --optimize-autoloader --no-interaction
        php artisan migrate --force
        php artisan config:cache
        php artisan route:cache
        php artisan queue:restart
        php artisan up
      ENDSSH
```

## Jenkins Pipeline

**Create Jenkinsfile:**
```groovy
// Jenkinsfile

pipeline {
    agent any
    
    environment {
        APP_NAME = 'MSWMS'
        APP_ENV = 'production'
        APP_DEBUG = 'false'
    }
    
    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }
        
        stage('Code Quality') {
            steps {
                sh '''
                    composer install --prefer-dist --no-progress
                    vendor/bin/pint --test
                    vendor/bin/phpstan analyse --memory-limit=1G
                '''
            }
        }
        
        stage('Tests') {
            steps {
                withCredentials([usernamePassword(credentialsId: 'db-credentials', usernameVariable: 'DB_USER', passwordVariable: 'DB_PASS')]) {
                    sh '''
                        php artisan key:generate
                        php artisan migrate --force
                        php artisan test --compact
                    '''
                }
            }
        }
        
        stage('Build') {
            steps {
                sh '''
                    npm ci
                    npm run build
                '''
            }
        }
        
        stage('Deploy Staging') {
            when {
                branch 'staging'
            }
            steps {
                sshagent(['deploy-staging-key']) {
                    sh '''
                        ssh -o StrictHostKeyChecking=no deploy@staging.mswms.example.com << 'ENDSSH'
                            cd /var/www/mswms
                            git pull origin staging
                            composer install --no-dev --optimize-autoloader
                            php artisan migrate --force
                            php artisan config:cache
                            php artisan route:cache
                            php artisan queue:restart
                        ENDSSH
                    '''
                }
            }
        }
        
        stage('Deploy Production') {
            when {
                branch 'main'
            }
            steps {
                input message: 'Deploy to production?', ok: 'Deploy'
                sshagent(['deploy-production-key']) {
                    sh '''
                        ssh -o StrictHostKeyChecking=no deploy@api.mswms.example.com << 'ENDSSH'
                            cd /var/www/mswms
                            php artisan down --secret="maintenance_jenkins"
                            git pull origin main
                            composer install --no-dev --optimize-autoloader
                            php artisan migrate --force
                            php artisan config:cache
                            php artisan route:cache
                            php artisan queue:restart
                            php artisan up
                        ENDSSH
                    '''
                }
            }
        }
    }
    
    post {
        always {
            cleanWs()
        }
        failure {
            mail to: 'team@example.com',
                 subject: "Pipeline Failed: ${currentBuild.fullDisplayName}",
                 body: "Check console output at ${BUILD_URL}"
        }
    }
}
```

## Deployment Automation Script

**Create Deploy Script:**
```bash
#!/bin/bash
# scripts/deploy.sh

set -e

# Configuration
ENVIRONMENT=${1:-staging}
APP_DIR="/var/www/mswms"
BACKUP_DIR="/var/backups/mswms"
DATE=$(date +%Y%m%d_%H%M%S)

echo "🚀 Deploying to $ENVIRONMENT..."

case $ENVIRONMENT in
    staging)
        BRANCH="staging"
        USER="deploy"
        HOST="staging.mswms.example.com"
        ;;
    production)
        BRANCH="main"
        USER="deploy"
        HOST="api.mswms.example.com"
        ;;
    *)
        echo "Invalid environment. Use 'staging' or 'production'"
        exit 1
        ;;
esac

# Deploy function
deploy() {
    echo "📦 Pulling latest code..."
    git pull origin $BRANCH
    
    echo "📦 Installing dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
    
    echo "📦 Building assets..."
    npm install --production
    npm run build
    
    echo "🗑️  Clearing caches..."
    php artisan config:clear
    php artisan cache:clear
    php artisan route:clear
    php artisan view:clear
    
    echo "🔄 Running migrations..."
    php artisan migrate --force
    
    echo "⚡ Optimizing..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    composer dump-autoload --classmap-authoritative
    
    echo "🔄 Restarting workers..."
    php artisan queue:restart
    
    echo "🔐 Setting permissions..."
    chmod -R 775 storage bootstrap/cache
    chown -R www-data:www-data storage bootstrap/cache
}

# Production-specific steps
if [ "$ENVIRONMENT" = "production" ]; then
    echo "🔧 Enabling maintenance mode..."
    php artisan down --secret="maintenance_manual" --retry=60
    
    echo "💾 Creating backup..."
    mkdir -p $BACKUP_DIR
    pg_dump -h localhost -U mswms_prod_user mswms_production | gzip > $BACKUP_DIR/pre_deploy_$DATE.sql.gz
    
    deploy
    
    echo "🧪 Running health check..."
    sleep 10
    HEALTH_STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://api.mswms.example.com/api/health)
    
    if [ "$HEALTH_STATUS" = "200" ]; then
        echo "✅ Health check passed"
        echo "🔓 Disabling maintenance mode..."
        php artisan up
        echo "✅ Production deployment complete!"
    else
        echo "❌ Health check failed (HTTP $HEALTH_STATUS)"
        echo "🔄 Rolling back..."
        php artisan migrate:rollback --force
        php artisan up
        exit 1
    fi
else
    deploy
    
    echo "🧪 Running health check..."
    sleep 10
    curl -f https://staging.mswms.example.com/api/health
    
    echo "✅ Staging deployment complete!"
fi
```

## Monitoring Deployments

### Deployment Notifications

**Slack Notification:**
```yaml
# Add to GitHub Actions workflow

- name: Notify Slack
  uses: 8398a7/action-slack@v3
  with:
    status: ${{ job.status }}
    text: |
      Deployment to ${{ needs.deploy-production.outputs.environment }}
      Commit: ${{ github.sha }}
      Author: ${{ github.actor }}
    webhook_url: ${{ secrets.SLACK_WEBHOOK }}
  if: always()
```

### Deployment Tracking

**Create Deployment Log:**
```bash
#!/bin/bash
# scripts/log-deployment.sh

LOG_FILE="/var/log/mswms/deployments.log"
DATE=$(date '+%Y-%m-%d %H:%M:%S')
USER=$(whoami)
ENVIRONMENT=$1
COMMIT=$(git rev-parse HEAD)
COMMIT_MSG=$(git log -1 --pretty=%B)

echo "[$DATE] Environment: $ENVIRONMENT | User: $USER | Commit: $COMMIT | Message: $COMMIT_MSG" >> $LOG_FILE
```

## Rollback Procedures

### Automated Rollback

**Add to CI/CD Pipeline:**
```yaml
- name: Rollback on Failure
  if: failure()
  run: |
    ssh deploy@api.mswms.example.com << 'ENDSSH'
      cd /var/www/mswms
      
      # Get previous commit
      git checkout HEAD~1
      
      # Restore database
      LATEST_BACKUP=$(ls -t /var/backups/mswms/*.sql.gz | head -1)
      gunzip -c $LATEST_BACKUP | psql -h localhost -U mswms_prod_user mswms_production
      
      # Clear caches
      php artisan config:clear
      php artisan cache:clear
      php artisan config:cache
      php artisan cache:clear
      
      # Restart services
      php artisan queue:restart
      sudo systemctl restart php8.3-fpm
      
      # Bring up
      php artisan up
    ENDSSH
```

---

**Previous Section**: [← Backup & Recovery](12-backup-recovery.md)  
**Next Section**: [Troubleshooting →](14-troubleshooting.md)

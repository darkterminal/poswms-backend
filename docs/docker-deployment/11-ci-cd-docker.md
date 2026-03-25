# CI/CD with Docker for MSWMS

## Overview

This document covers setting up Continuous Integration and Continuous Deployment (CI/CD) pipelines for MSWMS using Docker and free open-source tools.

## CI/CD Options

| Platform | Type | Cost | Best For |
|----------|------|------|----------|
| GitHub Actions | Cloud | Free tier | GitHub repos |
| GitLab CI | Cloud/Self | Free tier | GitLab repos |
| Jenkins | Self-hosted | Free | Custom workflows |
| Drone CI | Self-hosted | Free | Docker-native |

## GitHub Actions

### Workflow Configuration

**.github/workflows/docker-build.yml:**
```yaml
name: Docker Build and Push

on:
  push:
    branches: [main, staging]
  pull_request:
    branches: [main]

env:
  REGISTRY: ghcr.io
  IMAGE_NAME: ${{ github.repository }}

jobs:
  # Job 1: Build and Test
  test:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:15-alpine
        env:
          POSTGRES_DB: testing
          POSTGRES_USER: postgres
          POSTGRES_PASSWORD: password
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
      redis:
        image: redis:7-alpine
        options: >-
          --health-cmd "redis-cli ping"
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

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
        run: composer install --prefer-dist --no-progress

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

  # Job 2: Build Docker Image
  build:
    needs: test
    runs-on: ubuntu-latest
    if: github.event_name == 'push'
    permissions:
      contents: read
      packages: write

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v3

      - name: Login to Container Registry
        uses: docker/login-action@v3
        with:
          registry: ${{ env.REGISTRY }}
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Extract metadata
        id: meta
        uses: docker/metadata-action@v5
        with:
          images: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}
          tags: |
            type=ref,event=branch
            type=sha,prefix={{branch}}-
            type=semver,pattern={{version}}

      - name: Build and push
        uses: docker/build-push-action@v5
        with:
          context: .
          file: Dockerfile
          target: production
          push: true
          tags: ${{ steps.meta.outputs.tags }}
          labels: ${{ steps.meta.outputs.labels }}
          cache-from: type=gha
          cache-to: type=gha,mode=max
          platforms: linux/amd64

  # Job 3: Deploy to Staging
  deploy-staging:
    needs: build
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/staging'
    environment: staging

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Deploy to Staging
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.STAGING_HOST }}
          username: ${{ secrets.STAGING_USER }}
          key: ${{ secrets.STAGING_SSH_KEY }}
          script: |
            cd /var/www/mswms
            docker compose pull
            docker compose up -d --build
            docker compose exec -T app php artisan migrate --force

  # Job 4: Deploy to Production
  deploy-production:
    needs: deploy-staging
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    environment: production

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Enable Maintenance Mode
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.PRODUCTION_HOST }}
          username: ${{ secrets.PRODUCTION_USER }}
          key: ${{ secrets.PRODUCTION_SSH_KEY }}
          script: |
            cd /var/www/mswms
            docker compose exec -T app php artisan down --secret="maintenance_github" --retry=60

      - name: Create Backup
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.PRODUCTION_HOST }}
          username: ${{ secrets.PRODUCTION_USER }}
          key: ${{ secrets.PRODUCTION_SSH_KEY }}
          script: |
            cd /var/www/mswms
            docker compose exec -T postgres pg_dump -U mswms_prod_user mswms_production | gzip > /var/backups/mswms/pre_deploy_$(date +%Y%m%d_%H%M%S).sql.gz

      - name: Deploy to Production
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.PRODUCTION_HOST }}
          username: ${{ secrets.PRODUCTION_USER }}
          key: ${{ secrets.PRODUCTION_SSH_KEY }}
          script: |
            cd /var/www/mswms
            docker compose pull
            docker compose up -d --build
            docker compose exec -T app php artisan migrate --force
            docker compose exec -T app php artisan config:cache
            docker compose exec -T app php artisan route:cache
            docker compose exec -T app php artisan queue:restart

      - name: Health Check
        run: |
          sleep 30
          curl -f https://api.mswms.example.com/api/health || exit 1

      - name: Disable Maintenance Mode
        if: success()
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.PRODUCTION_HOST }}
          username: ${{ secrets.PRODUCTION_USER }}
          key: ${{ secrets.PRODUCTION_SSH_KEY }}
          script: |
            cd /var/www/mswms
            docker compose exec -T app php artisan up

      - name: Rollback on Failure
        if: failure()
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.PRODUCTION_HOST }}
          username: ${{ secrets.PRODUCTION_USER }}
          key: ${{ secrets.PRODUCTION_SSH_KEY }}
          script: |
            cd /var/www/mswms
            docker compose exec -T app php artisan migrate:rollback --force
            docker compose exec -T app php artisan up
```

### Required Secrets

Configure these in GitHub Repository Settings → Secrets:

```bash
# Staging
STAGING_HOST=staging.mswms.example.com
STAGING_USER=deploy
STAGING_SSH_KEY=<ssh_private_key>

# Production
PRODUCTION_HOST=api.mswms.example.com
PRODUCTION_USER=deploy
PRODUCTION_SSH_KEY=<ssh_private_key>

# Database
DB_PASSWORD=<database_password>
REDIS_PASSWORD=<redis_password>

# Other
APP_KEY=base64:<app_key>
```

## GitLab CI

### .gitlab-ci.yml

```yaml
stages:
  - test
  - build
  - deploy-staging
  - deploy-production

variables:
  DOCKER_REGISTRY: registry.gitlab.com
  DOCKER_IMAGE: $CI_PROJECT_PATH
  POSTGRES_DB: testing
  POSTGRES_USER: postgres
  POSTGRES_PASSWORD: password

# Test Stage
test:
  stage: test
  image: php:8.3-cli
  services:
    - postgres:15-alpine
    - redis:7-alpine
  
  before_script:
    - docker-php-ext-install pdo_pgsql
    - curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    - composer install --prefer-dist --no-progress
  
  script:
    - php artisan key:generate
    - php artisan migrate --force
    - php artisan test --compact
  
  variables:
    DB_CONNECTION: pgsql
    DB_HOST: postgres
    DB_DATABASE: testing
    REDIS_HOST: redis

# Build Stage
build:
  stage: build
  image: docker:24
  services:
    - docker:24-dind
  only:
    - main
    - staging
  
  before_script:
    - docker login -u $CI_REGISTRY_USER -p $CI_REGISTRY_PASSWORD $CI_REGISTRY
  
  script:
    - docker build -t $DOCKER_REGISTRY/$DOCKER_IMAGE:$CI_COMMIT_SHA .
    - docker push $DOCKER_REGISTRY/$DOCKER_IMAGE:$CI_COMMIT_SHA

# Deploy Staging
deploy-staging:
  stage: deploy-staging
  image: alpine:latest
  only:
    - staging
  environment: staging
  
  before_script:
    - apk add --no-cache openssh-client
    - eval $(ssh-agent -s)
    - echo "$STAGING_SSH_KEY" | ssh-add -
    - mkdir -p ~/.ssh
    - chmod 700 ~/.ssh
  
  script:
    - ssh -o StrictHostKeyChecking=no $STAGING_USER@$STAGING_HOST << 'ENDSSH'
        cd /var/www/mswms
        docker compose pull
        docker compose up -d --build
        docker compose exec -T app php artisan migrate --force
      ENDSSH

# Deploy Production
deploy-production:
  stage: deploy-production
  image: alpine:latest
  only:
    - main
  environment: production
  when: manual
  
  before_script:
    - apk add --no-cache openssh-client
    - eval $(ssh-agent -s)
    - echo "$PRODUCTION_SSH_KEY" | ssh-add -
    - mkdir -p ~/.ssh
    - chmod 700 ~/.ssh
  
  script:
    - ssh -o StrictHostKeyChecking=no $PRODUCTION_USER@$PRODUCTION_HOST << 'ENDSSH'
        cd /var/www/mswms
        docker compose exec -T app php artisan down --secret="maintenance_gitlab"
        docker compose pull
        docker compose up -d --build
        docker compose exec -T app php artisan migrate --force
        docker compose exec -T app php artisan up
      ENDSSH
```

## Self-Hosted CI/CD with Jenkins

### Jenkinsfile

```groovy
pipeline {
    agent any
    
    environment {
        DOCKER_REGISTRY = 'registry.example.com'
        DOCKER_IMAGE = 'mswms-backend'
    }
    
    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }
        
        stage('Test') {
            steps {
                sh '''
                    docker compose -f docker-compose.test.yml up -d
                    docker compose exec -T app php artisan test --compact
                    docker compose -f docker-compose.test.yml down
                '''
            }
        }
        
        stage('Build') {
            steps {
                sh '''
                    docker build -t ${DOCKER_REGISTRY}/${DOCKER_IMAGE}:${BUILD_ID} .
                    docker push ${DOCKER_REGISTRY}/${DOCKER_IMAGE}:${BUILD_ID}
                '''
            }
        }
        
        stage('Deploy Staging') {
            when {
                branch 'staging'
            }
            steps {
                sshagent(['staging-credentials']) {
                    sh '''
                        ssh ${STAGING_USER}@${STAGING_HOST} << 'ENDSSH'
                            cd /var/www/mswms
                            docker compose pull
                            docker compose up -d --build
                            docker compose exec -T app php artisan migrate --force
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
                sshagent(['production-credentials']) {
                    sh '''
                        ssh ${PRODUCTION_USER}@${PRODUCTION_HOST} << 'ENDSSH'
                            cd /var/www/mswms
                            docker compose exec -T app php artisan down
                            docker compose pull
                            docker compose up -d --build
                            docker compose exec -T app php artisan migrate --force
                            docker compose exec -T app php artisan up
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

## Docker Registry Setup

### Self-Hosted Registry

```yaml
# docker-compose.registry.yml
version: '3.8'

services:
  registry:
    image: registry:2
    container_name: mswms-registry
    restart: unless-stopped
    environment:
      - REGISTRY_AUTH=htpasswd
      - REGISTRY_AUTH_HTPASSWD_REALM=Registry
      - REGISTRY_AUTH_HTPASSWD_PATH=/auth/htpasswd
      - REGISTRY_STORAGE_DELETE_ENABLED=true
    volumes:
      - ./registry_data:/var/lib/registry
      - ./auth:/auth
      - ./config.yml:/etc/docker/registry/config.yml
    ports:
      - "5000:5000"
    networks:
      - mswms-network

  registry-ui:
    image: joxit/docker-registry-ui:latest
    container_name: mswms-registry-ui
    restart: unless-stopped
    environment:
      - DOCKER_REGISTRY_URL=http://registry:5000
      - SINGLE_REGISTRY=true
    ports:
      - "8083:80"
    networks:
      - mswms-network
    depends_on:
      - registry
```

### Push Image to Registry

```bash
# Tag image
docker tag mswms-backend:latest registry.example.com/mswms-backend:1.0.0

# Login
docker login registry.example.com

# Push
docker push registry.example.com/mswms-backend:1.0.0
```

## Deployment Strategies

### Blue-Green Deployment

```yaml
# docker-compose.blue.yml
services:
  app-blue:
    build: .
    deploy:
      replicas: 3

# docker-compose.green.yml
services:
  app-green:
    build: .
    deploy:
      replicas: 3

# Switch traffic
docker compose -f docker-compose.blue.yml down
docker compose -f docker-compose.green.yml up -d
```

### Rolling Update

```bash
# Kubernetes-style rolling update with Docker
for i in {1..4}; do
    docker compose stop app-$i
    docker compose up -d --no-deps app-$i
    sleep 10
    # Health check
    curl -f http://localhost/api/health || exit 1
done
```

### Canary Deployment

```yaml
# Deploy new version to 10% of instances
services:
  app-v1:
    image: mswms-backend:1.0.0
    deploy:
      replicas: 9
  
  app-v2:
    image: mswms-backend:2.0.0
    deploy:
      replicas: 1
```

## Monitoring Deployments

### Deployment Notifications

**Slack Notification:**
```yaml
- name: Notify Slack
  uses: 8398a7/action-slack@v3
  with:
    status: ${{ job.status }}
    text: |
      Deployment to ${{ github.ref }}
      Commit: ${{ github.sha }}
      Author: ${{ github.actor }}
    webhook_url: ${{ secrets.SLACK_WEBHOOK }}
  if: always()
```

### Deployment Tracking

```bash
#!/bin/bash
# scripts/log-deployment.sh

LOG_FILE="/var/log/mswms/deployments.log"
DATE=$(date '+%Y-%m-%d %H:%M:%S')
USER=$(whoami)
ENVIRONMENT=$1
COMMIT=$(git rev-parse HEAD)

echo "[$DATE] Env: $ENVIRONMENT | User: $USER | Commit: $COMMIT" >> $LOG_FILE
```

## Troubleshooting

### Build Failures

```bash
# Test build locally
docker build -t mswms-test .

# Check Dockerfile syntax
hadolint Dockerfile

# View build logs
docker build --progress=plain -t mswms-test . 2>&1 | tee build.log
```

### Deployment Issues

```bash
# Check container status
docker compose ps

# View logs
docker compose logs -f app

# Rollback
docker compose pull
docker compose up -d
```

---

**Previous Sections**: [Coolify Deployment](10-coolify-deployment.md), [SSL Certificates](08-ssl-certificates.md)  
**Next Section**: [Monitoring & Logging →](12-monitoring-logging.md)

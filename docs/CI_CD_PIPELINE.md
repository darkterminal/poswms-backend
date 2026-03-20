# CI/CD Pipeline Documentation

**Project:** Multi-Store & Warehouse Management System (MSWMS)  
**Framework:** Laravel 13.x (PHP 8.3)  
**CI/CD Platform:** GitHub Actions  
**Last Updated:** March 21, 2026

---

## Overview

The MSWMS CI/CD pipeline automates testing, code quality checks, and deployment processes using GitHub Actions. The pipeline ensures code quality and enables reliable deployments to staging and production environments.

---

## Pipeline Workflows

### 1. Tests (`tests.yml`)

**Trigger:** Push or pull request to `main` or `develop` branches

**Purpose:** Run the full test suite to ensure code functionality

**Environment:** Ubuntu latest with PostgreSQL 15

**Steps:**
1. Checkout code
2. Setup PHP 8.3 with required extensions
3. Install Composer dependencies
4. Setup test database (PostgreSQL)
5. Run migrations
6. Execute PHPUnit tests

**Configuration:**
```yaml
# .github/workflows/tests.yml
```

### 2. Code Quality (`code-quality.yml`)

**Trigger:** Push or pull request to `main` or `develop` branches

**Purpose:** Ensure code meets quality standards

**Jobs:**

#### Laravel Pint (Code Formatting)
- Checks code formatting against Laravel standards
- Fails if any files don't match the expected style

#### PHPStan (Static Analysis)
- Performs static code analysis
- Detects potential bugs and code smells
- Ensures type safety

**Configuration:**
```yaml
# .github/workflows/code-quality.yml
```

### 3. Deploy to Staging (`deploy-staging.yml`)

**Trigger:** Manual (workflow_dispatch) via GitHub Actions UI

**Purpose:** Manually deploy changes to staging environment

**Inputs:**
- `branch`: Branch to deploy (default: develop)
- `environment`: Environment to deploy to (staging)

**Environment:** `staging` (https://staging.mswms.example.com)

**Steps:**
1. Checkout code
2. Install dependencies (production only)
3. Copy staging environment configuration
4. Run database migrations
5. Cache configuration, routes, and views
6. Set proper file permissions
7. Restart queue workers

**Required Secrets:**
- `STAGING_APP_KEY`
- `STAGING_DB_HOST`
- `STAGING_DB_NAME`
- `STAGING_DB_USERNAME`
- `STAGING_DB_PASSWORD`

### 4. Deploy to Production (`deploy-production.yml`)

**Trigger:** Manual (workflow_dispatch) via GitHub Actions UI

**Purpose:** Manually deploy changes to production environment

**Inputs:**
- `branch`: Branch to deploy (default: main)
- `environment`: Environment to deploy to (production)

**Environment:** `production` (https://app.mswms.example.com)

**Steps:** Same as staging deployment

**Required Secrets:**
- `PRODUCTION_APP_KEY`
- `PRODUCTION_DB_HOST`
- `PRODUCTION_DB_NAME`
- `PRODUCTION_DB_USERNAME`
- `PRODUCTION_DB_PASSWORD`

---

## Branch Strategy

```
main ────────────────→ Production
                       ↑
                       │ Manual PR
                       │
develop ──────────────→ Staging
  ↑
  │ Feature branches
  │
feature/*
```

### Branch Protection Rules

**Main Branch:**
- Require pull request reviews
- Require status checks to pass (tests, code quality)
- Require branches to be up to date before merging
- Restrict direct pushes

**Develop Branch:**
- Require status checks to pass
- Require linear history (rebase before merge)

---

## Setup Instructions

### 1. GitHub Repository Setup

```bash
# Initialize git repository (if not already done)
git init
git remote add origin https://github.com/your-org/mswms-backend.git
```

### 2. Configure GitHub Secrets

Navigate to: **Repository Settings → Secrets and variables → Actions**

#### Staging Secrets

| Secret Name | Description | Example |
|-------------|-------------|---------|
| `STAGING_APP_KEY` | Laravel application key | `base64:abc123...` |
| `STAGING_DB_HOST` | Database host | `staging-db.example.com` |
| `STAGING_DB_NAME` | Database name | `mswms_staging` |
| `STAGING_DB_USERNAME` | Database username | `mswms_staging_user` |
| `STAGING_DB_PASSWORD` | Database password | `secure_password` |

#### Production Secrets

| Secret Name | Description | Example |
|-------------|-------------|---------|
| `PRODUCTION_APP_KEY` | Laravel application key | `base64:xyz789...` |
| `PRODUCTION_DB_HOST` | Database host | `production-db.example.com` |
| `PRODUCTION_DB_NAME` | Database name | `mswms_production` |
| `PRODUCTION_DB_USERNAME` | Database username | `mswms_production_user` |
| `PRODUCTION_DB_PASSWORD` | Database password | `super_secure_password` |

### 3. Configure GitHub Environments

Navigate to: **Repository Settings → Environments**

#### Staging Environment
- **Name:** `staging`
- **URL:** `https://staging.mswms.example.com`
- **Deployment branches:** `develop`

#### Production Environment
- **Name:** `production`
- **URL:** `https://app.mswms.example.com`
- **Deployment branches:** `main`
- **Required reviewers:** Add team members for approval

---

## Usage Guide

### Development Workflow

1. **Create Feature Branch**
   ```bash
   git checkout develop
   git checkout -b feature/new-feature
   ```

2. **Make Changes & Commit**
   ```bash
   git add .
   git commit -m "feat: add new feature"
   git push origin feature/new-feature
   ```

3. **Create Pull Request**
   - Target: `develop`
   - GitHub Actions will automatically run tests and code quality checks
   - Wait for all checks to pass
   - Request review from team members

4. **Merge to Develop**
   - Once approved and checks pass, merge to `develop`
   - Automatic deployment to staging

5. **Deploy to Production**
   - Create PR from `develop` to `main`
   - Review and approve
   - Merge to `main`
   - Automatic deployment to production

### Manual Deployment

**Deploy to Staging:**
1. Go to **Actions** tab in GitHub
2. Select **Deploy to Staging** workflow
3. Click **Run workflow**
4. Select branch (default: develop)
5. Click **Run workflow**

**Deploy to Production:**
1. Go to **Actions** tab in GitHub
2. Select **Deploy to Production** workflow
3. Click **Run workflow**
4. Select branch (default: main)
5. Click **Run workflow**
6. **Requires approval** if environment protection is enabled

---

## Monitoring & Troubleshooting

### View Workflow Runs

1. Navigate to: **Actions** tab in GitHub repository
2. Select workflow (Tests, Code Quality, Deploy Staging, Deploy Production)
3. Click on specific run to view details

### Common Issues

#### Tests Failing

**Symptoms:** Test job fails with assertion errors

**Resolution:**
```bash
# Run tests locally
php artisan test --compact

# Run specific test
php artisan test --compact --filter=TestName

# Check database connection
php artisan tinker --execute "DB::connection()->getPdo();"
```

#### Code Quality Failing

**Symptoms:** Pint or PHPStan reports errors

**Resolution:**
```bash
# Fix code formatting
vendor/bin/pint

# Check PHPStan errors
vendor/bin/phpstan analyse --memory-limit=1G
```

#### Deployment Failing

**Symptoms:** Deployment job fails with migration or permission errors

**Resolution:**
1. Check workflow logs for specific error
2. Verify database credentials in GitHub Secrets
3. Ensure database is accessible from GitHub Actions runner
4. Check file permissions on server

### Workflow Logs

Access detailed logs:
1. Go to **Actions** tab
2. Click on workflow run
3. Click on specific job
4. View real-time logs

---

## Customization

### Adding New Tests

Tests are automatically picked up by the workflow. Simply add test files to `tests/` directory following PHPUnit conventions.

### Modifying Code Quality Rules

**Laravel Pint:**
```bash
# Edit pint.json for custom rules
vendor/bin/pint --test
```

**PHPStan:**
```bash
# Edit phpstan.neon for custom rules
vendor/bin/phpstan analyse
```

### Custom Deployment Steps

Edit workflow files in `.github/workflows/`:
- `deploy-staging.yml`
- `deploy-production.yml`

Add steps before or after existing steps as needed.

---

## Security Best Practices

### 1. Protect Secrets

- Never commit `.env` files with real credentials
- Use GitHub Secrets for all sensitive data
- Rotate secrets regularly
- Limit secret access to specific environments

### 2. Branch Protection

- Enable branch protection for `main` and `develop`
- Require pull request reviews
- Require status checks to pass
- Prevent force pushes

### 3. Environment Protection

- Require reviewers for production deployments
- Set deployment delays for production
- Limit who can deploy to production

### 4. Dependency Review

- Review Composer dependency updates
- Use `composer audit` to check for vulnerabilities
- Keep dependencies up to date

---

## Performance Optimization

### Caching

GitHub Actions automatically caches:
- Composer dependencies
- PHP extensions

### Parallel Jobs

Tests and code quality run in parallel to reduce total CI time.

### Self-Hosted Runners (Optional)

For faster builds, consider setting up self-hosted runners:

```yaml
runs-on: [self-hosted, linux]
```

---

## Metrics & Reporting

### Workflow Status Badge

Add to README.md:

```markdown
[![Tests](https://github.com/your-org/mswms-backend/actions/workflows/tests.yml/badge.svg)](https://github.com/your-org/mswms-backend/actions/workflows/tests.yml)
[![Code Quality](https://github.com/your-org/mswms-backend/actions/workflows/code-quality.yml/badge.svg)](https://github.com/your-org/mswms-backend/actions/workflows/code-quality.yml)
```

### Deployment Status

Track deployment success rate and frequency in the **Actions** tab.

---

## Support

For CI/CD issues:

1. Check workflow logs in GitHub Actions
2. Review this documentation
3. Contact the development team

---

**Document Maintainer:** Development Team  
**Review Cycle:** Update when workflow changes are made

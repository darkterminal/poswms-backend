#!/bin/bash

# ==========================================
# POS WMS Backend - Docker Quick Start
# ==========================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
print_step() {
    echo -e "${BLUE}==> $1${NC}"
}

print_success() {
    echo -e "${GREEN}==> $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}==> $1${NC}"
}

print_error() {
    echo -e "${RED}==> $1${NC}"
}

check_prerequisites() {
    print_step "Checking prerequisites..."
    
    if ! command -v docker &> /dev/null; then
        print_error "Docker is not installed. Please install Docker first."
        exit 1
    fi
    
    if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
        print_error "Docker Compose is not installed. Please install Docker Compose first."
        exit 1
    fi
    
    print_success "Docker and Docker Compose are installed."
}

setup_environment() {
    print_step "Setting up environment..."
    
    if [ ! -f .env ]; then
        print_step "Creating .env file from .env.docker..."
        cp .env.docker .env
        print_success ".env file created."
    else
        print_warning ".env file already exists."
    fi
    
    # Check if APP_KEY is set
    if ! grep -q "^APP_KEY=" .env || [ -z "$(grep "^APP_KEY=" .env | cut -d'=' -f2)" ]; then
        print_step "Generating APP_KEY..."
        if command -v php &> /dev/null; then
            APP_KEY=$(php artisan key:generate --show 2>/dev/null | grep -oP 'base64:\K[A-Za-z0-9+/=]+')
            APP_KEY="base64:${APP_KEY}"
            sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" .env
            print_success "APP_KEY generated."
        else
            print_warning "PHP not found. Please generate APP_KEY manually or run 'php artisan key:generate' after setup."
        fi
    fi
    
    print_success "Environment setup complete."
}

start_containers() {
    print_step "Starting Docker containers..."
    
    if docker compose version &> /dev/null 2>&1; then
        DOCKER_COMPOSE_CMD="docker compose"
    else
        DOCKER_COMPOSE_CMD="docker-compose"
    fi
    
    $DOCKER_COMPOSE_CMD up -d --build
    print_success "Containers started."
}

run_migrations() {
    print_step "Waiting for database to be ready..."
    sleep 10
    
    print_step "Running database migrations..."
    
    if docker compose version &> /dev/null 2>&1; then
        DOCKER_COMPOSE_CMD="docker compose"
    else
        DOCKER_COMPOSE_CMD="docker-compose"
    fi
    
    $DOCKER_COMPOSE_CMD exec -T app php artisan migrate --force
    
    if [ $? -eq 0 ]; then
        print_success "Migrations completed."
    else
        print_warning "Migrations failed. You can run them manually later with: docker compose exec app php artisan migrate"
    fi
}

show_status() {
    echo ""
    print_success "=========================================="
    print_success "POS WMS Backend is now running!"
    print_success "=========================================="
    echo ""
    print_step "API URL: http://localhost:8080"
    print_step "Health Check: http://localhost:8080/api/health"
    print_step "API Documentation: http://localhost:8080/docs/api"
    echo ""
    print_step "Database: PostgreSQL on localhost:5432"
    print_step "  - Database: poswms"
    print_step "  - Username: poswms"
    print_step "  - Password: secret"
    echo ""
    print_step "Redis: localhost:6379"
    echo ""
    print_warning "Useful commands:"
    echo "  - View logs: docker compose logs -f"
    echo "  - Stop: docker compose down"
    echo "  - Restart: docker compose restart"
    echo "  - Access container: docker compose exec app bash"
    echo "  - Run artisan: docker compose exec app php artisan <command>"
    echo ""
}

# Main script
main() {
    echo ""
    print_success "=========================================="
    print_success "POS WMS Backend - Docker Setup"
    print_success "=========================================="
    echo ""
    
    check_prerequisites
    setup_environment
    start_containers
    run_migrations
    show_status
}

# Run main function
main

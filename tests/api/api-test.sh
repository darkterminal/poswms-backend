#!/bin/bash

# POS WMS API REST Client Wrapper
# This script provides a convenient way to run the REST client tests

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
REST_CLIENT="${SCRIPT_DIR}/RestClient.php"

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo -e "${RED}Error: PHP is not installed${NC}"
    exit 1
fi

# Check if REST client exists
if [ ! -f "${REST_CLIENT}" ]; then
    echo -e "${RED}Error: RestClient.php not found${NC}"
    exit 1
fi

# Display help
show_help() {
    cat << EOF
${BLUE}=============================================${NC}
${BLUE}POS WMS API - REST Client Test Runner${NC}
${BLUE}=============================================${NC}

${GREEN}Usage:${NC}
  ./api-test.sh [OPTIONS]

${GREEN}Options:${NC}
  -h, --help              Show this help message
  -a, --all               Run all tests
  -e, --endpoint NAME     Test specific endpoint
  -u, --url URL           Set base URL (default: http://localhost:8000)
  -t, --tenant ID         Set tenant ID (default: 1)
  -E, --email EMAIL       Set email (default: test@example.com)
  -P, --password PASS     Set password (default: password)
  -v, --verbose           Enable verbose output
  -q, --quick             Quick test (authentication only)

${GREEN}Examples:${NC}
  # Run all tests
  ./api-test.sh --all

  # Test products endpoint
  ./api-test.sh --endpoint products

  # Run with custom configuration
  ./api-test.sh --all --url http://localhost:8000 --tenant 1 --email admin@example.com

  # Quick authentication test
  ./api-test.sh --quick

  # Verbose output for orders
  ./api-test.sh --endpoint orders --verbose

${GREEN}Available Endpoints:${NC}
  authentication, stores, warehouses, categories, products,
  customers, inventory, orders, pricingTiers, pricingRules,
  roles, permissions, reports, webhooks, auditLogs

${BLUE}=============================================${NC}
EOF
}

# Default values
BASE_URL="http://localhost:8000"
TENANT_ID=1
EMAIL="admin@demo.com"
PASSWORD="password"
VERBOSE=""
ENDPOINT=""
RUN_ALL=false
QUICK_TEST=false

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -h|--help)
            show_help
            exit 0
            ;;
        -a|--all)
            RUN_ALL=true
            shift
            ;;
        -e|--endpoint)
            ENDPOINT="$2"
            shift 2
            ;;
        -u|--url)
            BASE_URL="$2"
            shift 2
            ;;
        -t|--tenant)
            TENANT_ID="$2"
            shift 2
            ;;
        -E|--email)
            EMAIL="$2"
            shift 2
            ;;
        -P|--password)
            PASSWORD="$2"
            shift 2
            ;;
        -v|--verbose)
            VERBOSE="--verbose"
            shift
            ;;
        -q|--quick)
            QUICK_TEST=true
            shift
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Build command
CMD="php ${REST_CLIENT}"
CMD+=" --base-url=${BASE_URL}"
CMD+=" --tenant=${TENANT_ID}"
CMD+=" --email=${EMAIL}"
CMD+=" --password=${PASSWORD}"

if [ -n "${VERBOSE}" ]; then
    CMD+=" ${VERBOSE}"
fi

if [ -n "${ENDPOINT}" ]; then
    CMD+=" --endpoint=${ENDPOINT}"
fi

# Run tests
echo -e "${BLUE}=============================================${NC}"
echo -e "${BLUE}POS WMS API - REST Client Tests${NC}"
echo -e "${BLUE}=============================================${NC}"
echo -e "${YELLOW}Base URL:${NC} ${BASE_URL}"
echo -e "${YELLOW}Tenant ID:${NC} ${TENANT_ID}"
echo -e "${YELLOW}Email:${NC} ${EMAIL}"
echo -e "${BLUE}=============================================${NC}"
echo ""

if [ "${QUICK_TEST}" = true ]; then
    echo -e "${GREEN}Running quick authentication test...${NC}"
    ENDPOINT="authentication"
    CMD+=" --endpoint=${ENDPOINT}"
    eval $CMD
elif [ "${RUN_ALL}" = true ] || [ -n "${ENDPOINT}" ]; then
    if [ -n "${ENDPOINT}" ]; then
        echo -e "${GREEN}Testing endpoint: ${ENDPOINT}${NC}"
    else
        echo -e "${GREEN}Running all tests...${NC}"
    fi
    eval $CMD
else
    echo -e "${YELLOW}No action specified. Use --all to run all tests or --endpoint to test specific endpoint.${NC}"
    echo ""
    show_help
fi

echo ""
echo -e "${BLUE}=============================================${NC}"

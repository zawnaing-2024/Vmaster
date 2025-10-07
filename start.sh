#!/bin/bash

echo "🔒 VPN CMS Portal - Starting..."
echo "================================"
echo ""

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed. Please install Docker first."
    exit 1
fi

# Check if Docker Compose is installed
if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

# Create uploads directory if it doesn't exist
mkdir -p uploads/qrcodes
chmod -R 755 uploads

echo "✅ Docker and Docker Compose are installed"
echo ""

# Stop any existing containers
echo "🛑 Stopping any existing containers..."
docker-compose down

echo ""
echo "🚀 Starting Docker containers..."
docker-compose up -d

echo ""
echo "⏳ Waiting for services to be ready..."
sleep 10

# Check if containers are running
if [ "$(docker ps -q -f name=vpn_cms_web)" ] && [ "$(docker ps -q -f name=vpn_cms_db)" ]; then
    echo ""
    echo "✅ VPN CMS Portal is now running!"
    echo ""
    echo "================================"
    echo "📝 Access Information:"
    echo "================================"
    echo ""
    echo "🌐 Main Portal:     http://localhost:8080"
    echo "👨‍💼 Admin Panel:     http://localhost:8080/admin/login.php"
    echo "👤 Customer Panel:  http://localhost:8080/customer/login.php"
    echo "🗄️  phpMyAdmin:      http://localhost:8081"
    echo ""
    echo "================================"
    echo "🔑 Default Credentials:"
    echo "================================"
    echo ""
    echo "Admin Login:"
    echo "  Username: admin"
    echo "  Password: admin123"
    echo ""
    echo "⚠️  IMPORTANT: Change the default password after first login!"
    echo ""
    echo "================================"
    echo "📚 Useful Commands:"
    echo "================================"
    echo ""
    echo "Stop:    docker-compose down"
    echo "Restart: docker-compose restart"
    echo "Logs:    docker-compose logs -f"
    echo ""
else
    echo ""
    echo "❌ Failed to start containers. Please check the logs:"
    echo "   docker-compose logs"
fi


#!/usr/bin/env python3
"""
Test script for Sistema de Audio Centralizado
Basic functionality tests
"""

import requests
import time
import subprocess
import sys
import signal
import os

def test_audio_system():
    """Test the audio system functionality"""
    print("=== Testing Sistema de Audio Centralizado ===")
    
    # Start the server in background
    print("Starting server...")
    server_process = subprocess.Popen([
        sys.executable, "app.py"
    ], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    
    # Wait for server to start
    time.sleep(3)
    
    base_url = "http://localhost:5000"
    
    try:
        # Test 1: Main page loads
        print("Test 1: Main page accessibility...")
        response = requests.get(base_url, timeout=5)
        assert response.status_code == 200
        assert "Sistema de Audio Centralizado" in response.text
        print("✓ Main page loads correctly")
        
        # Test 2: Upload page loads
        print("Test 2: Upload page accessibility...")
        response = requests.get(f"{base_url}/upload", timeout=5)
        assert response.status_code == 200
        assert "Subir Archivos" in response.text
        print("✓ Upload page loads correctly")
        
        # Test 3: API status endpoint
        print("Test 3: API status endpoint...")
        response = requests.get(f"{base_url}/api/status", timeout=5)
        assert response.status_code == 200
        data = response.json()
        assert "current_song" in data
        assert "is_playing" in data
        assert "volume" in data
        print("✓ API status endpoint works")
        
        # Test 4: Volume control
        print("Test 4: Volume control...")
        response = requests.get(f"{base_url}/api/volume/50", timeout=5)
        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "success"
        print("✓ Volume control works")
        
        # Test 5: Stop functionality
        print("Test 5: Stop functionality...")
        response = requests.get(f"{base_url}/api/stop", timeout=5)
        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "success"
        print("✓ Stop functionality works")
        
        print("\n=== All tests passed! ===")
        return True
        
    except Exception as e:
        print(f"❌ Test failed: {e}")
        return False
        
    finally:
        # Stop the server
        print("Stopping server...")
        server_process.terminate()
        try:
            server_process.wait(timeout=5)
        except subprocess.TimeoutExpired:
            server_process.kill()

if __name__ == "__main__":
    success = test_audio_system()
    sys.exit(0 if success else 1)
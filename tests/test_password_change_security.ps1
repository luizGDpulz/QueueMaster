# Test password change security
# Execution: .\test_password_change_security.ps1

$BaseUrl = "http://localhost/api/v1"
$Email = "admin@example.com"
$OldPassword = "password123"
$NewPassword = "password1234"

Write-Host "Security Test: Token Revocation on Password Change" -ForegroundColor Cyan
Write-Host "====================================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: Login
Write-Host "Step 1: Login with current password..." -ForegroundColor Yellow
$loginBody = @{
    email = $Email
    password = $OldPassword
} | ConvertTo-Json

try {
    $loginResponse = Invoke-RestMethod -Uri "$BaseUrl/auth/login" -Method Post `
        -ContentType "application/json" -Body $loginBody
    
    $AccessToken = $loginResponse.data.access_token
    $RefreshToken = $loginResponse.data.refresh_token
    $UserId = $loginResponse.data.user.id

    Write-Host "[OK] Login successful" -ForegroundColor Green
    Write-Host "   User ID: $UserId"
    Write-Host "   Access Token: $($AccessToken.Substring(0,20))..."
    Write-Host "   Refresh Token: $($RefreshToken.Substring(0,20))..."
    Write-Host ""
} catch {
    Write-Host "[FAIL] Login failed!" -ForegroundColor Red
    Write-Host $_.Exception.Message
    exit 1
}

# Step 2: Change password
Write-Host "Step 2: Changing password..." -ForegroundColor Yellow
$changeBody = @{
    current_password = $OldPassword
    password = $NewPassword
} | ConvertTo-Json

try {
    $headers = @{
        "Authorization" = "Bearer $AccessToken"
        "Content-Type" = "application/json"
    }
    
    $changeResponse = Invoke-RestMethod -Uri "$BaseUrl/users/$UserId" -Method Put `
        -Headers $headers -Body $changeBody
    
    Write-Host "[OK] Password changed successfully" -ForegroundColor Green
    Write-Host ""
} catch {
    Write-Host "[FAIL] Failed to change password!" -ForegroundColor Red
    Write-Host $_.Exception.Message
    exit 1
}

# Step 3: Try using old refresh token (SHOULD FAIL)
Write-Host "Step 3: Trying OLD refresh token (should fail)..." -ForegroundColor Yellow
$refreshBody = @{
    refresh_token = $RefreshToken
} | ConvertTo-Json

try {
    $refreshResponse = Invoke-RestMethod -Uri "$BaseUrl/auth/refresh" -Method Post `
        -ContentType "application/json" -Body $refreshBody -ErrorAction Stop
    
    Write-Host "[FAIL] SECURITY ISSUE: Refresh token still works!" -ForegroundColor Red
} catch {
    Write-Host "[OK] Refresh token REVOKED correctly (secure)" -ForegroundColor Green
    Write-Host "   Status: $($_.Exception.Response.StatusCode.value__)"
}
Write-Host ""

# Step 4: Try login with old password (SHOULD FAIL)
Write-Host "Step 4: Trying login with OLD password (should fail)..." -ForegroundColor Yellow
$oldLoginBody = @{
    email = $Email
    password = $OldPassword
} | ConvertTo-Json

try {
    $oldLoginResponse = Invoke-RestMethod -Uri "$BaseUrl/auth/login" -Method Post `
        -ContentType "application/json" -Body $oldLoginBody -ErrorAction Stop
    
    Write-Host "[FAIL] PROBLEM: Login with old password still works!" -ForegroundColor Red
} catch {
    Write-Host "[OK] Login with old password FAILED correctly" -ForegroundColor Green
    Write-Host "   Status: $($_.Exception.Response.StatusCode.value__)"
}
Write-Host ""

# Step 5: Login with new password (SHOULD WORK)
Write-Host "Step 5: Trying login with NEW password (should work)..." -ForegroundColor Yellow
$newLoginBody = @{
    email = $Email
    password = $NewPassword
} | ConvertTo-Json

try {
    $newLoginResponse = Invoke-RestMethod -Uri "$BaseUrl/auth/login" -Method Post `
        -ContentType "application/json" -Body $newLoginBody
    
    $NewAccessToken = $newLoginResponse.data.access_token
    
    Write-Host "[OK] Login with new password WORKED correctly" -ForegroundColor Green
    Write-Host "   New Access Token: $($NewAccessToken.Substring(0,20))..."
    Write-Host ""
} catch {
    Write-Host "[FAIL] Failed to login with new password!" -ForegroundColor Red
    Write-Host $_.Exception.Message
    exit 1
}

# Step 6: Revert password
Write-Host "Step 6: Reverting password to original value..." -ForegroundColor Yellow
$revertBody = @{
    current_password = $NewPassword
    password = $OldPassword
} | ConvertTo-Json

try {
    $headers = @{
        "Authorization" = "Bearer $NewAccessToken"
        "Content-Type" = "application/json"
    }
    
    $revertResponse = Invoke-RestMethod -Uri "$BaseUrl/users/$UserId" -Method Put `
        -Headers $headers -Body $revertBody
    
    Write-Host "[OK] Password reverted to $OldPassword" -ForegroundColor Green
} catch {
    Write-Host "[WARN] Could not revert password" -ForegroundColor Yellow
}
Write-Host ""

Write-Host "====================================================" -ForegroundColor Cyan
Write-Host "Test Complete!" -ForegroundColor Cyan
Write-Host ""
Write-Host "Result summary:"
Write-Host "  [OK] = Correct behavior" -ForegroundColor Green
Write-Host "  [FAIL] = Security issue" -ForegroundColor Red

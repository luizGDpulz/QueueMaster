# Teste de Segurança: Troca de Senha com cURL
# Execute cada comando em sequência

Write-Host "=====================================" -ForegroundColor Cyan
Write-Host "Teste: Troca de Senha e Invalidacao" -ForegroundColor Cyan
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host ""

# Limpar log
Remove-Item C:\xampp\htdocs\public\logs\app-2026-01-23.log -Force -ErrorAction SilentlyContinue
Write-Host "[OK] Log limpo" -ForegroundColor Green
Write-Host ""

# 1. LOGIN
Write-Host "1. Fazendo login..." -ForegroundColor Yellow
$loginResult = Invoke-RestMethod -Uri "http://localhost:8080/api/v1/auth/login" `
  -Method Post `
  -ContentType "application/json" `
  -Body '{"email":"admin@example.com","password":"password123"}'

$token = $loginResult.data.access_token
$userId = $loginResult.data.user.id

if ($token) {
    Write-Host "[OK] Login bem-sucedido" -ForegroundColor Green
    Write-Host "    User ID: $userId" -ForegroundColor Gray
    Write-Host "    Token: $($token.Substring(0,30))..." -ForegroundColor Gray
    Write-Host ""
} else {
    Write-Host "[ERRO] Login falhou!" -ForegroundColor Red
    exit 1
}

Start-Sleep -Seconds 1

# 2. PRIMEIRA TROCA DE SENHA
Write-Host "2. Trocando senha (password123 -> password1234)..." -ForegroundColor Yellow
$change1 = Invoke-RestMethod -Uri "http://localhost:8080/api/v1/users/$userId" `
  -Method Put `
  -Headers @{"Authorization"="Bearer $token"} `
  -ContentType "application/json" `
  -Body '{"current_password":"password123","password":"password1234"}'

if ($change1.success) {
    Write-Host "[OK] Senha trocada com sucesso!" -ForegroundColor Green
    Write-Host "    Mensagem: $($change1.data.message)" -ForegroundColor Gray
    Write-Host ""
} else {
    Write-Host "[ERRO] Falha ao trocar senha!" -ForegroundColor Red
    Write-Host "    Erro: $($change1.error.message)" -ForegroundColor Red
    exit 1
}

Start-Sleep -Seconds 1

# 3. TENTATIVA COM SENHA ANTIGA (DEVE FALHAR)
Write-Host "3. Tentando trocar NOVAMENTE com senha ANTIGA (deve falhar)..." -ForegroundColor Yellow
try {
    $change2 = Invoke-RestMethod -Uri "http://localhost:8080/api/v1/users/$userId" `
      -Method Put `
      -Headers @{"Authorization"="Bearer $token"} `
      -ContentType "application/json" `
      -Body '{"current_password":"password123","password":"password1234"}' `
      -ErrorAction Stop
} catch {
    $change2 = $_.ErrorDetails.Message | ConvertFrom-Json
}

if ($change2.success) {
    Write-Host "[PROBLEMA!] Ainda aceitou senha antiga!" -ForegroundColor Red
    Write-Host "    Isso indica cache ou problema na validacao" -ForegroundColor Red
    Write-Host ""
} else {
    Write-Host "[OK] Senha antiga REJEITADA corretamente!" -ForegroundColor Green
    Write-Host "    Erro: $($change2.error.message)" -ForegroundColor Gray
    Write-Host "    Codigo: $($change2.error.code)" -ForegroundColor Gray
    Write-Host ""
}

Start-Sleep -Seconds 1

# 4. LOGIN COM SENHA ANTIGA (DEVE FALHAR)
Write-Host "4. Tentando login com senha ANTIGA (deve falhar)..." -ForegroundColor Yellow
try {
    $loginOld = Invoke-RestMethod -Uri "http://localhost:8080/api/v1/auth/login" `
      -Method Post `
      -ContentType "application/json" `
      -Body '{"email":"admin@example.com","password":"password123"}' `
      -ErrorAction Stop
} catch {
    $loginOld = $_.ErrorDetails.Message | ConvertFrom-Json
}

if ($loginOld.success) {
    Write-Host "[PROBLEMA!] Login com senha antiga funcionou!" -ForegroundColor Red
    Write-Host ""
} else {
    Write-Host "[OK] Login com senha antiga FALHOU!" -ForegroundColor Green
    Write-Host "    Erro: $($loginOld.error.message)" -ForegroundColor Gray
    Write-Host ""
}

Start-Sleep -Seconds 1

# 5. LOGIN COM SENHA NOVA (DEVE FUNCIONAR)
Write-Host "5. Fazendo login com senha NOVA (deve funcionar)..." -ForegroundColor Yellow
$loginNew = Invoke-RestMethod -Uri "http://localhost:8080/api/v1/auth/login" `
  -Method Post `
  -ContentType "application/json" `
  -Body '{"email":"admin@example.com","password":"password1234"}'

if ($loginNew.success) {
    Write-Host "[OK] Login com senha nova funcionou!" -ForegroundColor Green
    $newToken = $loginNew.data.access_token
    Write-Host "    Novo Token: $($newToken.Substring(0,30))..." -ForegroundColor Gray
    Write-Host ""
} else {
    Write-Host "[PROBLEMA!] Login com senha nova falhou!" -ForegroundColor Red
    Write-Host "    Erro: $($loginNew.error.message)" -ForegroundColor Red
    Write-Host ""
    exit 1
}

Start-Sleep -Seconds 1

# 6. REVERTER SENHA
Write-Host "6. Revertendo senha para password123..." -ForegroundColor Yellow
try {
    $revert = Invoke-RestMethod -Uri "http://localhost:8080/api/v1/users/$userId" `
      -Method Put `
      -Headers @{"Authorization"="Bearer $newToken"} `
      -ContentType "application/json" `
      -Body '{"current_password":"password1234","password":"password123"}'
} catch {
    $revert = @{success=$false}
}

if ($revert.success) {
    Write-Host "[OK] Senha revertida!" -ForegroundColor Green
    Write-Host ""
} else {
    Write-Host "[AVISO] Nao foi possivel reverter senha" -ForegroundColor Yellow
    Write-Host ""
}

# 7. MOSTRAR LOGS
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host "LOGS DA API" -ForegroundColor Cyan
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host ""

$logFile = "C:\xampp\htdocs\public\logs\app-2026-01-23.log"
if (Test-Path $logFile) {
    Write-Host "Tentativas de troca de senha:" -ForegroundColor Yellow
    Write-Host ""
    
    Get-Content $logFile | Where-Object {$_ -match "Password"} | ForEach-Object {
        $log = $_ | ConvertFrom-Json
        
        $color = "White"
        if ($log.message -match "successfully") { $color = "Green" }
        if ($log.message -match "rejected") { $color = "Red" }
        
        Write-Host "[$($log.timestamp)] " -NoNewline -ForegroundColor Gray
        Write-Host "$($log.message)" -ForegroundColor $color
        
        if ($log.context.is_valid -ne $null) {
            $validColor = if ($log.context.is_valid) { "Green" } else { "Red" }
            Write-Host "  -> is_valid: $($log.context.is_valid)" -ForegroundColor $validColor
            Write-Host "  -> request_id: $($log.request_id)" -ForegroundColor Gray
        }
        Write-Host ""
    }
} else {
    Write-Host "[AVISO] Arquivo de log nao encontrado" -ForegroundColor Yellow
}

Write-Host "=====================================" -ForegroundColor Cyan
Write-Host "TESTE CONCLUIDO" -ForegroundColor Cyan
Write-Host "=====================================" -ForegroundColor Cyan

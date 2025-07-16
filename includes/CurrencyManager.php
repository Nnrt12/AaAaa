<?php
/**
 * Cryptocurrency Currency Manager
 * Handles USDT, BTC and other cryptocurrency operations
 */

class CurrencyManager {
    private $pdo;
    private $supported_currencies = [
        'USDT' => [
            'name' => 'Tether',
            'symbol' => 'USDT',
            'decimals' => 6,
            'min_amount' => 1.0,
            'network' => 'TRC20',
            'icon' => '₮'
        ],
        'USDT_BEP20' => [
            'name' => 'Tether (BEP20)',
            'symbol' => 'USDT',
            'decimals' => 18,
            'min_amount' => 1.0,
            'network' => 'BEP20',
            'icon' => '₮',
            'display_name' => 'USDT (BEP20)'
        ],
        'BTC' => [
            'name' => 'Bitcoin',
            'symbol' => 'BTC',
            'decimals' => 8,
            'min_amount' => 0.0001,
            'network' => 'Bitcoin',
            'icon' => '₿'
        ],
        'ETH' => [
            'name' => 'Ethereum',
            'symbol' => 'ETH',
            'decimals' => 18,
            'min_amount' => 0.001,
            'network' => 'ERC20',
            'icon' => 'Ξ'
        ],
        'BCH' => [
            'name' => 'Bitcoin Cash',
            'symbol' => 'BCH',
            'decimals' => 8,
            'min_amount' => 0.001,
            'network' => 'Bitcoin Cash',
            'icon' => '₿'
        ],
        'TRX' => [
            'name' => 'TRON',
            'symbol' => 'TRX',
            'decimals' => 6,
            'min_amount' => 10.0,
            'network' => 'TRON',
            'icon' => '⚡'
        ],
        'XMR' => [
            'name' => 'Monero',
            'symbol' => 'XMR',
            'decimals' => 12,
            'min_amount' => 0.01,
            'network' => 'Monero',
            'icon' => 'ɱ'
        ],
        'DASH' => [
            'name' => 'Dash',
            'symbol' => 'DASH',
            'decimals' => 8,
            'min_amount' => 0.01,
            'network' => 'Dash',
            'icon' => 'Đ'
        ],
        'ZEC' => [
            'name' => 'Zcash',
            'symbol' => 'ZEC',
            'decimals' => 8,
            'min_amount' => 0.01,
            'network' => 'Zcash',
            'icon' => 'ⓩ'
        ]
    ];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->initializeCurrencies();
    }
    
    /**
     * Initialize currencies in database
     */
    private function initializeCurrencies() {
        try {
            // Create currencies table if not exists
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS currencies (
                    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
                    code VARCHAR(10) UNIQUE NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    symbol VARCHAR(10) NOT NULL,
                    decimals INT DEFAULT 8,
                    min_amount DECIMAL(20,8) DEFAULT 0.00000001,
                    network VARCHAR(50) NULL,
                    icon VARCHAR(10) NULL,
                    is_active BOOLEAN DEFAULT TRUE,
                    exchange_rate DECIMAL(20,8) DEFAULT 1.00000000,
                    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_code (code),
                    INDEX idx_active (is_active)
                )
            ");
            
            // Insert supported currencies
            foreach ($this->supported_currencies as $code => $currency) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO currencies (code, name, symbol, decimals, min_amount, network, icon, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE 
                        name = VALUES(name),
                        symbol = VALUES(symbol),
                        decimals = VALUES(decimals),
                        min_amount = VALUES(min_amount),
                        network = VALUES(network),
                        icon = VALUES(icon)
                ");
                $stmt->execute([
                    $code,
                    $currency['name'],
                    $currency['symbol'],
                    $currency['decimals'],
                    $currency['min_amount'],
                    $currency['network'],
                    $currency['icon']
                ]);
            }
            
        } catch (Exception $e) {
            error_log('Currency initialization failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get all active currencies
     */
    public function getActiveCurrencies() {
        $stmt = $this->pdo->prepare("SELECT * FROM currencies WHERE is_active = 1 ORDER BY code");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get currency by code
     */
    public function getCurrency($code) {
        $stmt = $this->pdo->prepare("SELECT * FROM currencies WHERE code = ? AND is_active = 1");
        $stmt->execute([$code]);
        return $stmt->fetch();
    }
    
    /**
     * Convert USD to cryptocurrency
     */
    public function convertUSDToCrypto($usd_amount, $crypto_code) {
        $currency = $this->getCurrency($crypto_code);
        if (!$currency) {
            throw new Exception("Currency {$crypto_code} not found");
        }
        
        // Get current exchange rate (you can integrate with external APIs)
        $exchange_rate = $this->getExchangeRate($crypto_code);
        
        return $usd_amount / $exchange_rate;
    }
    
    /**
     * Convert cryptocurrency to USD
     */
    public function convertCryptoToUSD($crypto_amount, $crypto_code) {
        $currency = $this->getCurrency($crypto_code);
        if (!$currency) {
            throw new Exception("Currency {$crypto_code} not found");
        }
        
        // Get current exchange rate
        $exchange_rate = $this->getExchangeRate($crypto_code);
        
        return $crypto_amount * $exchange_rate;
    }
    
    /**
     * Get exchange rate for cryptocurrency
     */
    public function getExchangeRate($crypto_code) {
        // Try to get live rates from Plisio first
        try {
            $plisio_key = getSetting('plisio_api_key');
            if ($plisio_key) {
                require_once __DIR__ . '/PlisioClient.php';
                $client = new PlisioClient($plisio_key);
                $currencies = $client->getCurrencies('USD');
                
                if (isset($currencies[$crypto_code]['rate_usd'])) {
                    return floatval($currencies[$crypto_code]['rate_usd']);
                }
            }
        } catch (Exception $e) {
            error_log('Failed to get live rates from Plisio: ' . $e->getMessage());
        }
        
        // Fallback to static rates if API fails
        $fallback_rates = [
            'USDT' => 1.00,
            'USDT_BEP20' => 1.00,
            'BTC' => 45000.00,
            'ETH' => 2500.00,
            'BCH' => 300.00,
            'TRX' => 0.06,
            'XMR' => 150.00,
            'DASH' => 40.00,
            'ZEC' => 30.00
        ];
        
        return $fallback_rates[$crypto_code] ?? 1.00;
    }
    
    /**
     * Update exchange rates
     */
    public function updateExchangeRates() {
        try {
            // Get live rates from Plisio
            $plisio_key = getSetting('plisio_api_key');
            if (!$plisio_key) {
                return false;
            }
            
            require_once __DIR__ . '/PlisioClient.php';
            $client = new PlisioClient($plisio_key);
            $currencies = $client->getCurrencies('USD');
            
            if (!is_array($currencies)) {
                return false;
            }
            
            $supported_codes = array_keys($this->supported_currencies);
            
            foreach ($supported_codes as $code) {
                if (isset($currencies[$code]['rate_usd'])) {
                    $rate = floatval($currencies[$code]['rate_usd']);
                    $stmt = $this->pdo->prepare("
                        UPDATE currencies 
                        SET exchange_rate = ?, last_updated = NOW() 
                        WHERE code = ?
                    ");
                    $stmt->execute([$rate, $code]);
                }
            }
            
            return true;
        } catch (Exception $e) {
            error_log('Exchange rate update failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get display name for currency
     */
    public function getDisplayName($currency_code) {
        $currency = $this->getCurrency($currency_code);
        if (!$currency) {
            return $currency_code;
        }
        
        // Use display_name if available, otherwise use name
        return $currency['display_name'] ?? $currency['name'];
    }
    
    /**
     * Format currency amount
     */
    public function formatAmount($amount, $currency_code) {
        $currency = $this->getCurrency($currency_code);
        if (!$currency) {
            return number_format($amount, 8);
        }
        
        return number_format($amount, $currency['decimals'], '.', '');
    }
    
    /**
     * Validate cryptocurrency address
     */
    public function validateAddress($address, $currency_code) {
        switch ($currency_code) {
            case 'BTC':
            case 'BCH':
                return $this->validateBitcoinAddress($address);
            case 'ETH':
            case 'USDT':
            case 'USDT_BEP20':
                return $this->validateEthereumAddress($address);
            case 'TRX':
                return $this->validateTronAddress($address);
            case 'XMR':
                return $this->validateMoneroAddress($address);
            case 'DASH':
                return $this->validateDashAddress($address);
            case 'ZEC':
                return $this->validateZcashAddress($address);
            default:
                return strlen($address) >= 20; // Basic validation
        }
    }
    
    /**
     * Validate Bitcoin address
     */
    private function validateBitcoinAddress($address) {
        // Basic Bitcoin address validation
        if (preg_match('/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/', $address)) {
            return true; // Legacy address
        }
        if (preg_match('/^bc1[a-z0-9]{39,59}$/', $address)) {
            return true; // Bech32 address
        }
        return false;
    }
    
    /**
     * Validate Ethereum address
     */
    private function validateEthereumAddress($address) {
        // Validate Ethereum/BSC address format (both use same format)
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
    }
    
    /**
     * Validate TRON address
     */
    private function validateTronAddress($address) {
        return preg_match('/^T[A-Za-z1-9]{33}$/', $address);
    }
    
    /**
     * Validate Monero address
     */
    private function validateMoneroAddress($address) {
        return preg_match('/^[48][0-9AB][1-9A-HJ-NP-Za-km-z]{93}$/', $address);
    }
    
    /**
     * Validate Dash address
     */
    private function validateDashAddress($address) {
        return preg_match('/^X[1-9A-HJ-NP-Za-km-z]{33}$/', $address);
    }
    
    /**
     * Validate Zcash address
     */
    private function validateZcashAddress($address) {
        return preg_match('/^t1[0-9a-zA-Z]{33}$/', $address) || preg_match('/^zs1[0-9a-z]{75}$/', $address);
    }
    
    /**
     * Get currency statistics
     */
    public function getCurrencyStats() {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.crypto_currency as currency,
                COUNT(*) as transaction_count,
                SUM(p.amount) as total_volume
            FROM payments p 
            WHERE p.crypto_currency IS NOT NULL 
            AND p.status = 'completed'
            GROUP BY p.crypto_currency
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
?>
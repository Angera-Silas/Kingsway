<?php
declare(strict_types=1);

namespace App\API\Services\payments;

use PDO;
use RuntimeException;
use App\API\Services\FinancialPostingCoordinator;

/** Resolves incoming money to fees or transport without guessing. */
class PaymentRoutingService
{
    private $db;
    private ReferenceNormalizer $normalizer;
    private FinancialPostingCoordinator $posting;

    public function __construct(PDO $db) { $this->db = $db; $this->normalizer = new ReferenceNormalizer(); $this->posting = new FinancialPostingCoordinator($db); }

    public function generateReference(string $purpose, int $studentId, ?int $transportIntentId = null, ?int $uniformSaleId = null): array
    {
        if (!in_array($purpose, ['fees','transport','uniforms'], true) || !$studentId) throw new RuntimeException('purpose and student_id are required');
        if ($purpose === 'transport' && !$transportIntentId) throw new RuntimeException('transport_intent_id is required for a transport payment reference');
        if ($purpose === 'uniforms' && !$uniformSaleId) throw new RuntimeException('uniform_sale_id is required for a uniform payment reference');
        $s=$this->db->prepare('SELECT admission_no FROM students WHERE id=? AND status IN ("active","enrolled")'); $s->execute([$studentId]); $admission=$s->fetchColumn();
        if (!$admission) throw new RuntimeException('Active student not found');
        $prefix=$purpose==='transport'?'TRN':($purpose==='uniforms'?'U':'FEE'); $reference=$prefix.'-'.$admission.'-'.strtoupper(bin2hex(random_bytes(4)));
        $normalizedReference = $this->normalizer->reference($reference);
        $i=$this->db->prepare('INSERT INTO payment_routing_references (reference,normalized_reference,purpose,student_id,transport_intent_id,uniform_sale_id,expires_at) VALUES (?,?,?,?,?,?,DATE_ADD(NOW(), INTERVAL 30 DAY))'); $i->execute([$reference,$normalizedReference,$purpose,$studentId,$transportIntentId,$uniformSaleId]);
        return ['reference'=>$reference,'purpose'=>$purpose,'student_id'=>$studentId,'expires_at'=>date('Y-m-d H:i:s',time()+2592000)];
    }

    public function routeIncoming(string $providerCode, array $payload, string $providerTransactionId, float $amount, ?string $accountIdentifier = null, ?string $reference = null): array
    {
        if ($amount <= 0 || $providerTransactionId === '') throw new RuntimeException('Provider transaction ID and positive amount are required');
        $providerId=$this->providerId($providerCode); $accountIdentifier=$accountIdentifier ?: $this->extract($payload,['creditAccountIdentifier','ShortCode','BusinessShortCode','accountNumber','account_identifier','tillNumber']); $reference=$reference ?: $this->extract($payload,['BillRefNumber','customerReference','customer_reference','businessKey','invoiceNumber','invoice_number','accountReference','narration']);
        $rawReference=trim((string)$reference); $reference=$this->normalizer->reference($rawReference); $accountIdentifier=$this->normalizer->accountIdentifier($accountIdentifier);
        $routePurpose=$this->accountPurpose($providerId,$accountIdentifier);
        $referenceRow=$this->reference($rawReference);
        $referencePurpose=$referenceRow['purpose'] ?? $this->prefixPurpose($reference);
        if ($routePurpose && $referencePurpose && $routePurpose !== $referencePurpose) return $this->unmatched($providerId,$providerTransactionId,$accountIdentifier,$amount,$rawReference,$reference,'conflict','Collection account and reference identify different ledgers',$payload);
        $purpose=$referencePurpose ?: $routePurpose;
        if (!$purpose || !$referenceRow) {
            // Separate transport collection accounts may use the student's
            // admission number as the customer reference. Same-account
            // routing still requires an explicit TRN reference.
            if ($purpose === 'transport' && preg_match('/^T-(.+)$/i', $reference)) {
                $admission = trim((string)preg_replace('/^T-/i', '', $reference));
                $s=$this->db->prepare('SELECT id FROM students WHERE admission_no=? AND status IN ("active","enrolled") LIMIT 1'); $s->execute([$admission]); $studentId=(int)$s->fetchColumn();
                if ($studentId) {
                    $e=$this->db->prepare("SELECT e.id FROM student_transport_entitlements e JOIN transport_entitlement_periods p ON p.id=e.period_id WHERE e.student_id=? AND e.entitlement_status='active' AND p.period_start<=CURDATE() AND p.period_end>=CURDATE() ORDER BY DATEDIFF(p.period_end,p.period_start) ASC,e.id DESC LIMIT 1"); $e->execute([$studentId]); $entitlementId=(int)$e->fetchColumn();
                    $financialAccountId = $this->accountFinancialId($providerId, $accountIdentifier);
                    if ($entitlementId && $financialAccountId && (new TransportPaymentService($this->db))->reconcileEntitlement($entitlementId,$studentId,$amount,$providerCode,$providerTransactionId,$financialAccountId)) return ['status'=>'processed','purpose'=>'transport','student_id'=>$studentId,'reference'=>$reference,'provider_transaction_id'=>$providerTransactionId];
                }
            }
            return $this->unmatched($providerId,$providerTransactionId,$accountIdentifier,$amount,$rawReference,$reference,$purpose ?: 'unknown',$rawReference ? 'Reference is not registered' : 'Payment has no routable reference or account',$payload);
        }
        if ($routePurpose && $routePurpose !== $purpose) return $this->unmatched($providerId,$providerTransactionId,$accountIdentifier,$amount,$rawReference,$reference,'conflict','Routing conflict',$payload);
        if ($purpose==='transport') {
            $ok=(new TransportPaymentService($this->db))->reconcileIntentReference($reference,$amount,$providerCode,$providerTransactionId);
            if (!$ok) return $this->unmatched($providerId,$providerTransactionId,$accountIdentifier,$amount,$rawReference,$reference,'transport','Transport reference has no active payment intent',$payload);
        } elseif ($purpose==='uniforms') {
            $ok=(new UniformPaymentService($this->db))->reconcileReference($reference,$amount,$providerCode,$providerTransactionId,$this->accountFinancialId($providerId,$accountIdentifier));
            if (!$ok) return $this->unmatched($providerId,$providerTransactionId,$accountIdentifier,$amount,$rawReference,$reference,'uniforms','Uniform reference has no active sale/payment intent',$payload);
        } else {
            $financialAccountId=$this->accountFinancialId($providerId,$accountIdentifier);
            if (!$financialAccountId) return $this->unmatched($providerId,$providerTransactionId,$accountIdentifier,$amount,$rawReference,$reference,'fees','Fees collection account is not mapped to a financial ledger account',$payload);
            $this->recordFee($referenceRow['student_id'],$amount,$providerCode,$providerTransactionId,$payload,$financialAccountId);
        }
        $this->db->prepare("UPDATE payment_routing_references SET status='consumed' WHERE id=? AND status='active'")->execute([(int)$referenceRow['id']]);
        return ['status'=>'processed','purpose'=>$purpose,'student_id'=>(int)$referenceRow['student_id'],'reference'=>$reference,'provider_transaction_id'=>$providerTransactionId];
    }

    public function listRoutes(): array { return $this->db->query("SELECT r.*,p.code provider_code FROM payment_collection_routes r JOIN payment_providers p ON p.id=r.provider_id WHERE r.active=1 ORDER BY p.code,r.account_identifier,r.purpose")->fetchAll(PDO::FETCH_ASSOC); }

    public function isConfiguredAccount(string $providerCode, ?string $account): bool
    { if (!$account) return false; $pid=$this->providerId($providerCode); $normalized=$this->normalizer->accountIdentifier($account); $s=$this->db->prepare('SELECT 1 FROM payment_collection_routes WHERE provider_id=? AND normalized_account_identifier=? AND active=1 LIMIT 1'); $s->execute([$pid,$normalized]); return (bool)$s->fetchColumn(); }

    public function saveRoute(array $data): array
    {
        $code=(string)($data['provider_code']??''); $purpose=(string)($data['purpose']??''); $account=trim((string)($data['account_identifier']??'')); $prefix=trim((string)($data['reference_prefix']??($purpose==='transport'?'TRN':'FEE')));
        $financialAccountId=(int)($data['financial_account_id']??0);
        if (!$code||!in_array($purpose,['fees','transport','uniforms'],true)||$account===''||$prefix===''||$financialAccountId<=0) throw new RuntimeException('provider_code, purpose, account_identifier, financial_account_id and reference_prefix are required');
        $pid=$this->providerId($code); $normalizedAccount=$this->normalizer->accountIdentifier($account); $s=$this->db->prepare('INSERT INTO payment_collection_routes (provider_id,financial_account_id,account_identifier,normalized_account_identifier,purpose,reference_prefix) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE financial_account_id=VALUES(financial_account_id),reference_prefix=VALUES(reference_prefix),normalized_account_identifier=VALUES(normalized_account_identifier),active=1'); $s->execute([$pid,$financialAccountId,$account,$normalizedAccount,$purpose,$prefix]); return ['id'=>(int)$this->db->lastInsertId(),'provider_code'=>$code,'financial_account_id'=>$financialAccountId,'account_identifier'=>$account,'purpose'=>$purpose,'reference_prefix'=>$prefix];
    }

    public function listUnmatchedCases(array $filters=[]): array
    { $sql='SELECT u.*,p.code provider_code FROM payment_unmatched_cases u JOIN payment_providers p ON p.id=u.provider_id WHERE u.status=? ORDER BY u.created_at DESC LIMIT 300'; $s=$this->db->prepare($sql); $s->execute([$filters['status']??'unmatched']); return $s->fetchAll(PDO::FETCH_ASSOC); }

    public function resolveCase(int $id, array $data, int $userId): array
    {
        $purpose=(string)($data['purpose']??''); $studentId=(int)($data['student_id']??0); $this->db->beginTransaction();
        try {
            $s=$this->db->prepare("SELECT * FROM payment_unmatched_cases WHERE id=? AND status='unmatched' FOR UPDATE"); $s->execute([$id]); $c=$s->fetch(PDO::FETCH_ASSOC); if (!$c) throw new RuntimeException('Unmatched payment case not found');
            if ($purpose==='fees') { if (!$studentId || !(int)($data['financial_account_id']??0)) throw new RuntimeException('student_id and financial_account_id are required'); $this->recordFee($studentId,(float)$c['amount'],$c['provider_code']??'generic_bank',$c['provider_transaction_id'] ?: ('UNMATCHED-'.$id),json_decode($c['raw_payload'],true)?:[],(int)$data['financial_account_id']); }
            elseif ($purpose==='transport') { $entitlement=(int)($data['entitlement_id']??0); $financialAccountId=(int)($data['financial_account_id']??0); if (!$studentId||!$entitlement||!$financialAccountId) throw new RuntimeException('student_id, entitlement_id and financial_account_id are required'); (new TransportPaymentService($this->db))->reconcileEntitlement($entitlement,$studentId,(float)$c['amount'],$c['provider_code']??'manual',$c['provider_transaction_id'] ?: ('UNMATCHED-'.$id),$financialAccountId); }
            elseif ($purpose==='uniforms') { $saleId=(int)($data['uniform_sale_id']??0); $financialAccountId=(int)($data['financial_account_id']??0); if (!$saleId||!$financialAccountId) throw new RuntimeException('uniform_sale_id and financial_account_id are required'); (new UniformPaymentService($this->db))->recordManualSale($saleId,(float)$c['amount'],$c['provider_transaction_id'] ?: ('UNMATCHED-'.$id),$userId,$financialAccountId); }
            else throw new RuntimeException('purpose must be fees, transport or uniforms');
            $this->db->prepare("UPDATE payment_unmatched_cases SET status='resolved',purpose_candidate=?,resolved_student_id=?,resolved_by=?,resolved_at=NOW() WHERE id=?")->execute([$purpose,$studentId,$userId,$id]); $this->db->commit(); return ['id'=>$id,'status'=>'resolved','purpose'=>$purpose,'student_id'=>$studentId];
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    private function recordFee(int $studentId,float $amount,string $provider,string $reference,array $payload,int $financialAccountId): void
    {
        $s=$this->db->prepare('SELECT id FROM payments WHERE reference=? LIMIT 1'); $s->execute([$reference]); if ($s->fetchColumn()) return;
        $p=$this->db->prepare('SELECT parent_id FROM student_parents WHERE student_id=? ORDER BY is_primary_contact DESC,id LIMIT 1'); $p->execute([$studentId]); $parent=$p->fetchColumn() ?: null;
        $sp=$this->db->prepare('CALL sp_process_student_payment(?,?,?,?,?,?,?,?,?)'); $sp->execute([$studentId,$parent,$amount,$provider==='mpesa_daraja'?'mpesa':'bank_transfer',$reference,strtoupper($provider).'-'.$reference,1,date('Y-m-d H:i:s'),'Routed incoming payment: '.json_encode($payload)]); $sp->closeCursor();
        $find=$this->db->prepare('SELECT id FROM payments WHERE reference=? ORDER BY id DESC LIMIT 1'); $find->execute([$reference]); $paymentId=(int)$find->fetchColumn();
        if (!$paymentId) throw new RuntimeException('Fee payment procedure did not create a payment record.');
        $this->db->prepare('UPDATE payments SET financial_account_id=?,payment_purpose=? WHERE id=?')->execute([$financialAccountId,'fees',$paymentId]);
        $this->posting->postIncoming('payment',$paymentId,$financialAccountId,'fees',(string)$amount,0,$reference);
    }

    private function providerId(string $code): int { $s=$this->db->prepare('SELECT id FROM payment_providers WHERE code=? AND active=1 ORDER BY environment=IF(?="production", "production", "sandbox") DESC,id LIMIT 1'); $env=defined('APP_ENV')?(string)APP_ENV:'sandbox'; $s->execute([$code,$env]); $id=$s->fetchColumn(); if (!$id) throw new RuntimeException('Payment provider is not configured: '.$code); return (int)$id; }
    private function accountPurpose(int $providerId,string $account): ?string { if ($account==='') return null; $s=$this->db->prepare('SELECT purpose FROM payment_collection_routes WHERE provider_id=? AND normalized_account_identifier=? AND active=1'); $s->execute([$providerId,$account]); $rows=$s->fetchAll(PDO::FETCH_COLUMN); return count($rows)===1?(string)$rows[0]:null; }
    private function reference(string $ref): ?array { $normalized=$this->normalizer->reference($ref); if ($normalized==='') return null; $s=$this->db->prepare('SELECT * FROM payment_routing_references WHERE (normalized_reference=? OR reference=?) AND status IN ("active","consumed") AND (expires_at IS NULL OR expires_at>=NOW()) LIMIT 1'); $s->execute([$normalized,$ref]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
    private function prefixPurpose(string $ref): ?string { return $this->normalizer->purposeFromReference($ref); }
    private function accountFinancialId(int $providerId, string $account): ?int { $s=$this->db->prepare('SELECT financial_account_id FROM payment_collection_routes WHERE provider_id=? AND normalized_account_identifier=? AND active=1 LIMIT 1'); $s->execute([$providerId,$account]); $id=$s->fetchColumn(); return $id ? (int)$id : null; }
    private function extract(array $payload,array $keys): ?string { foreach ($keys as $key) if (isset($payload[$key]) && $payload[$key]!=='') return (string)$payload[$key]; return null; }
    private function unmatched(int $providerId,string $tx, string $account,float $amount,string $rawReference,string $normalizedReference,string $purpose,string $reason,array $payload): array { $s=$this->db->prepare('INSERT INTO payment_unmatched_cases (provider_id,provider_transaction_id,account_identifier,amount,reference_value,normalized_reference,purpose_candidate,reason,raw_payload) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE reason=VALUES(reason),normalized_reference=VALUES(normalized_reference),raw_payload=VALUES(raw_payload)'); $s->execute([$providerId,$tx,$account?:null,$amount,$rawReference?:null,$normalizedReference?:null,$purpose,$reason,json_encode($payload)]); return ['status'=>'unmatched','reason'=>$reason,'provider_transaction_id'=>$tx,'reference'=>$rawReference,'normalized_reference'=>$normalizedReference]; }
}

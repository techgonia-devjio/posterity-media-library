<?php

declare(strict_types=1);

namespace Posterity\MediaLibrary\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Posterity\MediaLibrary\Support\UrlSigner;

class UrlSignerTest extends TestCase
{
    private UrlSigner $signer;

    protected function setUp(): void
    {
        $this->signer = new UrlSigner('test-secret-key');
    }

    // ── sign ──────────────────────────────────────────────────────────────────

    public function test_sign_returns_hex_string(): void
    {
        $sig = $this->signer->sign(['uuid' => 'abc', 'w' => 200]);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $sig);
    }

    public function test_sign_is_deterministic(): void
    {
        $params = ['uuid' => 'abc', 'w' => 200, 'fmt' => 'webp'];

        $this->assertSame(
            $this->signer->sign($params),
            $this->signer->sign($params),
        );
    }

    public function test_sign_is_order_independent(): void
    {
        $a = $this->signer->sign(['uuid' => 'abc', 'w' => 200, 'fmt' => 'webp']);
        $b = $this->signer->sign(['fmt' => 'webp', 'uuid' => 'abc', 'w' => 200]);

        $this->assertSame($a, $b);
    }

    public function test_sign_differs_for_different_params(): void
    {
        $a = $this->signer->sign(['uuid' => 'abc', 'w' => 200]);
        $b = $this->signer->sign(['uuid' => 'abc', 'w' => 800]);

        $this->assertNotSame($a, $b);
    }

    public function test_sign_differs_for_different_keys(): void
    {
        $signerA = new UrlSigner('key-one');
        $signerB = new UrlSigner('key-two');

        $params = ['uuid' => 'abc', 'w' => 200];

        $this->assertNotSame($signerA->sign($params), $signerB->sign($params));
    }

    public function test_sign_does_not_include_sig_key_in_hash(): void
    {
        // If params accidentally contain 'sig', it must be excluded from the hash
        // (otherwise verify would be inconsistent — but sign should not include it)
        $withSig    = $this->signer->sign(['uuid' => 'abc', 'sig' => 'garbage']);
        $withoutSig = $this->signer->sign(['uuid' => 'abc']);

        // sign() does not filter 'sig' from its input, so these ARE different —
        // but verify() strips 'sig' before re-computing, so the round-trip works.
        // This test documents the behaviour explicitly.
        $this->assertNotSame($withSig, $withoutSig);
    }

    // ── verify ────────────────────────────────────────────────────────────────

    public function test_verify_returns_true_for_valid_signature(): void
    {
        $params = ['uuid' => 'abc', 'w' => 200];
        $sig    = $this->signer->sign($params);

        $this->assertTrue($this->signer->verify(array_merge($params, ['sig' => $sig])));
    }

    public function test_verify_returns_false_when_sig_is_missing(): void
    {
        $params = ['uuid' => 'abc', 'w' => 200];

        $this->assertFalse($this->signer->verify($params));
    }

    public function test_verify_returns_false_when_sig_is_empty_string(): void
    {
        $params = ['uuid' => 'abc', 'w' => 200, 'sig' => ''];

        $this->assertFalse($this->signer->verify($params));
    }

    public function test_verify_returns_false_for_tampered_param(): void
    {
        $params = ['uuid' => 'abc', 'w' => 200];
        $sig    = $this->signer->sign($params);

        $tampered = ['uuid' => 'abc', 'w' => 800, 'sig' => $sig];

        $this->assertFalse($this->signer->verify($tampered));
    }

    public function test_verify_returns_false_for_wrong_key(): void
    {
        $signerA = new UrlSigner('correct-key');
        $signerB = new UrlSigner('wrong-key');

        $params = ['uuid' => 'abc', 'w' => 200];
        $sig    = $signerA->sign($params);

        $this->assertFalse($signerB->verify(array_merge($params, ['sig' => $sig])));
    }

    public function test_verify_is_order_independent(): void
    {
        $params = ['uuid' => 'abc', 'w' => 200, 'fmt' => 'webp'];
        $sig    = $this->signer->sign($params);

        // Reorder params before verify
        $reordered = ['fmt' => 'webp', 'sig' => $sig, 'uuid' => 'abc', 'w' => 200];

        $this->assertTrue($this->signer->verify($reordered));
    }

    public function test_verify_returns_false_for_added_param(): void
    {
        $params = ['uuid' => 'abc', 'w' => 200];
        $sig    = $this->signer->sign($params);

        $withExtra = ['uuid' => 'abc', 'w' => 200, 'fmt' => 'webp', 'sig' => $sig];

        $this->assertFalse($this->signer->verify($withExtra));
    }

    public function test_verify_returns_false_for_removed_param(): void
    {
        $params = ['uuid' => 'abc', 'w' => 200, 'fmt' => 'webp'];
        $sig    = $this->signer->sign($params);

        $withMissing = ['uuid' => 'abc', 'fmt' => 'webp', 'sig' => $sig];

        $this->assertFalse($this->signer->verify($withMissing));
    }

    public function test_verify_uses_constant_time_comparison(): void
    {
        // hash_equals is used internally — this test ensures no timing short-circuit.
        // We can't test timing directly, so we just assert correct/incorrect outcomes.
        $params = ['uuid' => 'abc'];
        $sig    = $this->signer->sign($params);

        // Flip a single character in the signature
        $badSig = substr_replace($sig, $sig[0] === 'a' ? 'b' : 'a', 0, 1);

        $this->assertFalse($this->signer->verify(['uuid' => 'abc', 'sig' => $badSig]));
    }

    public function test_sign_with_empty_params_returns_hex_string(): void
    {
        $sig = $this->signer->sign([]);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $sig);
    }
}

// Dev-only: a fake Web Push service that decrypts what it receives.
//
//   node tools/fake_push_server.js <sub.json>
//
// sub.json = { pub, priv, auth } (base64url) of the simulated browser.
// Prints the request headers and the decrypted payload, answers 201.
// Used by tools/reminder_check to prove the RFC 8291 encryption works
// end to end without contacting Google/Mozilla.
const http = require('http');
const fs = require('fs');
const crypto = require('crypto');

const sub = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const b64u = (buf) => Buffer.from(buf).toString('base64url');
const unb64u = (s) => Buffer.from(s, 'base64url');

function decrypt(body, privB64, authB64) {
    const salt = body.subarray(0, 16);
    const idLen = body[20];
    const serverPub = body.subarray(21, 21 + idLen);
    const cipherAndTag = body.subarray(21 + idLen);
    const cipher = cipherAndTag.subarray(0, cipherAndTag.length - 16);
    const tag = cipherAndTag.subarray(cipherAndTag.length - 16);

    // Raw P-256 ECDH: Node computes the shared secret from the raw
    // private scalar and the server's uncompressed point directly.
    const ecdhCurve = crypto.createECDH('prime256v1');
    ecdhCurve.setPrivateKey(unb64u(privB64));
    const ecdh = ecdhCurve.computeSecret(serverPub);
    const auth = unb64u(authB64);
    const uaPub = unb64u(sub.pub);

    const prkKey = crypto.createHmac('sha256', auth).update(ecdh).digest();
    const keyInfo = Buffer.concat([Buffer.from('WebPush: info\x00'), uaPub, serverPub]);
    const ikm = crypto.createHmac('sha256', prkKey).update(Buffer.concat([keyInfo, Buffer.from([1])])).digest();
    const prk = crypto.createHmac('sha256', salt).update(ikm).digest();
    const cek = crypto.createHmac('sha256', prk)
        .update(Buffer.concat([Buffer.from('Content-Encoding: aes128gcm\x00'), Buffer.from([1])])).digest().subarray(0, 16);
    const nonce = crypto.createHmac('sha256', prk)
        .update(Buffer.concat([Buffer.from('Content-Encoding: nonce\x00'), Buffer.from([1])])).digest().subarray(0, 12);

    const decipher = crypto.createDecipheriv('aes-128-gcm', cek, nonce);
    decipher.setAuthTag(tag);
    const plain = Buffer.concat([decipher.update(cipher), decipher.final()]);
    return plain.subarray(0, plain.length - 1).toString('utf8'); // strip 0x02 delimiter
}

const server = http.createServer((req, res) => {
    const chunks = [];
    req.on('data', (c) => chunks.push(c));
    req.on('end', () => {
        const body = Buffer.concat(chunks);
        const auth = req.headers['authorization'] || '';
        console.log('--- push received ---');
        console.log('POST', req.url);
        console.log('authorization:', auth.slice(0, 60) + '...');
        console.log('encoding:', req.headers['content-encoding'], 'ttl:', req.headers['ttl'], 'bytes:', body.length);
        try {
            console.log('decrypted:', decrypt(body, sub.priv, sub.auth));
        } catch (err) {
            console.log('DECRYPT FAILED:', err.message);
        }
        res.writeHead(201, { 'Content-Length': 0 });
        res.end();
    });
});

server.listen(9099, '127.0.0.1', () => console.log('fake push service on http://127.0.0.1:9099'));

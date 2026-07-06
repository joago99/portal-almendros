import ftplib, os, pathlib

HOST = '217.196.54.191'
USER = 'u752254808'
PASS = 'HOSTFTjpagp20.'
ROOT = '/domains/constructoralosalmendros.cl/public_html/portal'
FILES = ['api/resumen.php', 'public/app.php']

ftp = ftplib.FTP(timeout=30)
ftp.connect(HOST, 21)
ftp.login(USER, PASS)

errors = []
for rel in FILES:
    local = pathlib.Path('C:/Users/joaqu/portal-almendros') / rel
    remote = ROOT + '/' + rel
    remote_dir = os.path.dirname(remote).replace('\\', '/')
    try:
        parts = remote_dir.split('/')
        cur = '/'
        for p in parts:
            if not p:
                continue
            cur += p + '/'
            try:
                ftp.mkd(cur)
            except ftplib.error_perm:
                pass
        with open(local, 'rb') as f:
            ftp.storbinary('STOR ' + remote, f)
        print('OK', rel)
    except Exception as e:
        errors.append((rel, str(e)))

ftp.quit()
print('ERRORS', errors)

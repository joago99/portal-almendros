from ftplib import FTP
import os
import io

HOST = '217.196.54.191'
USER = 'u752254808'
PASS = 'HOSTFTjpagp20.'
REMOTE_ROOT = '/domains/constructoralosalmendros.cl/public_html/portal'
LOCAL_DIR = r'C:\Users\joaqu\portal-almendros'

SKIP_EXT = {'.pyc', '.py', '.md', '.git', '.env', '.env.local', '.next', 'node_modules'}
UPLOAD_EXT = {'.php', '.html', '.css', '.js', '.json', '.db', '.sql', '.txt', '.png', '.jpg', '.jpeg', '.gif', '.ico', '.svg'}

ftp = FTP()
ftp.connect(HOST, 21, timeout=20)
ftp.login(USER, PASS)
ftp.cwd(REMOTE_ROOT)

def should_upload(path):
    _, ext = os.path.splitext(path)
    if ext.lower() in SKIP_EXT:
        return False
    if ext.lower() not in UPLOAD_EXT:
        return False
    return True

count = 0
for root, dirs, files in os.walk(LOCAL_DIR):
    # skip hidden dirs like .git
    dirs[:] = [d for d in dirs if not d.startswith('.') and d != 'node_modules' and d != '.next']
    rel = os.path.relpath(root, LOCAL_DIR)
    remote_dir = REMOTE_ROOT + '/' + rel.replace('\\', '/')
    try:
        ftp.cwd(remote_dir)
    except Exception:
        try:
            ftp.mkd(remote_dir)
            ftp.cwd(remote_dir)
        except Exception:
            pass
    for f in files:
        local_path = os.path.join(root, f)
        if not should_upload(local_path):
            continue
        data = open(local_path, 'rb').read()
        bio = io.BytesIO(data)
        try:
            ftp.storbinary('STOR ' + f, bio)
            count += 1
        except Exception as e:
            print('upload failed', local_path, e)
        bio.close()

print(f'uploaded {count} files')
ftp.quit()

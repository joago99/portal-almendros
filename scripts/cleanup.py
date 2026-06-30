from ftplib import FTP
import io

ftp = FTP()
ftp.connect('217.196.54.191', 21, timeout=10)
ftp.login('u752254808', 'HOSTFTjpagp20.')

# Clean up temp files
ftp.cwd('/domains/constructoralosalmendros.cl/public_html/portal')
for f in ['fix_schema.php', 'fix_null.php', 'delete_fix.php']:
    try:
        ftp.delete(f)
        print(f'deleted {f}')
    except:
        print(f'not found {f}')

# Fix projects.php
ftp.cwd('api')
with io.BytesIO() as bf:
    ftp.retrbinary('RETR projects.php', bf.write)
    c = bf.getvalue().decode()

c = c.replace(
    'error_reporting(E_ALL);\nini_set(\'display_errors\', 1);\n',
    ''
)

with io.BytesIO(c.encode()) as bf:
    ftp.storbinary('STOR projects.php', bf)
print('fixed projects.php')
ftp.quit()

import ftplib
HOST='217.196.54.191'; USER='u752254808'; PASS='HOSTFTjpagp20.'
ROOT='/domains/constructoralosalmendros.cl/public_html/portal'
FILES={
  'api/resumen.php':['Proyectos finalizados','Ratio pagado terminados','overflow-x:auto'],
  'public/app.php':['expandProjectAfterLoad','navigateFromHash','projectIdFromHash']
}
ftp=ftplib.FTP(timeout=30); ftp.connect(HOST,21); ftp.login(USER,PASS)
errors=[]
for rel, markers in FILES.items():
    path=ROOT+'/'+rel
    lines=[]
    def cb(line): lines.append(line.decode('utf-8','ignore')[:260])
    try:
        ftp.retrbinary('RETR '+path, cb, blocksize=2048)
        body=''.join(lines)
        miss=[m for m in markers if m not in body]
        print(rel, len(body), 'OK' if not miss else 'MISS:'+','.join(miss))
    except Exception as e:
        errors.append((rel,e))
ftp.quit()
print('ERRORS',errors)

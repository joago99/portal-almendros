from ftplib import FTP
import re

ftp = FTP()
ftp.connect("217.196.54.191", 21, timeout=10)
ftp.login("u752254808", "HOSTFTjpagp20.")
ftp.cwd("/domains/constructoralosalmendros.cl/public_html/portal/api")

with open("/tmp/projects_raw.php", "wb") as f:
    ftp.retrbinary("RETR projects.php", f.write)

with open("/tmp/projects_raw.php", "r") as f:
    c = f.read()

# Remove any backslash before $
c = re.sub(r'\\(?=\$)', '', c)

with open("/tmp/projects_clean.php", "w") as f:
    f.write(c)

with open("/tmp/projects_clean.php", "rb") as f:
    ftp.storbinary("STOR projects.php", f)

print("✅ Fixed and uploaded")

# Verify
with open("/tmp/projects_clean.php", "r") as f:
    if '\\$' in f.read():
        print("⚠️ Still has backslash-dollar")
    else:
        print("✅ All clean")

ftp.quit()

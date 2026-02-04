# SSH Tunnel Issue - Port Forwarding Disabled

## The Problem

The SSH tunnel to the remote MySQL database cannot work because the hosting provider has disabled SSH port forwarding.

### Error Message

When attempting to connect through the tunnel:

```
channel 2: open failed: administratively prohibited: open failed
ERROR 2013 (HY000): Lost connection to MySQL server at 'reading initial communication packet'
```

### Technical Details

- The remote SSH server has `AllowTcpForwarding no` set in `/etc/ssh/sshd_config`
- This is a security measure that prevents SSH tunneling/port forwarding
- The tunnel appears to start locally but all connection attempts fail
- This is controlled by the hosting provider, not us

## Verified Working

The following **do** work:
- ✓ SSH authentication to the remote server
- ✓ MySQL is running on the remote server (port 3306)
- ✓ MySQL accepts TCP connections on 127.0.0.1:3306
- ✓ MySQL has `bind_address = 0.0.0.0`
- ✓ Database credentials are correct

The **only** issue is SSH port forwarding being blocked.

## Solutions (in order of preference)

### 1. Enable SSH Port Forwarding (Best)

Contact the hosting provider and request:

```
Please enable SSH port forwarding on my account by setting:
AllowTcpForwarding yes
in /etc/ssh/sshd_config
```

Once enabled, the existing tunnel scripts will work immediately.

### 2. Remote MySQL Access (Good)

If the hosting provider offers it:
- Enable remote MySQL access in control panel (cPanel/Plesk)
- Create a user allowed from your IP address
- Connect directly without SSH tunnel

Update `dev-config.php` to use direct connection instead of tunnel.

### 3. Database Snapshots (Workable)

Export and import database periodically:

```bash
# On remote server
mysqldump -u urjxzpmdrd -p urjxzpmdrd wp_edd_* > edd-backup.sql

# Download and import locally
mysql -h localhost -P 3307 -u root vgp_edd_dev < edd-backup.sql
```

Loses real-time data but works for development.

### 4. VPN/Jump Host (Complex)

Set up a VPN or jump host that has both:
- Access to your local machine
- Access to the remote MySQL server

## Current Status

- Sync functionality has been removed (no longer needed)
- Tunnel scripts remain for when port forwarding is enabled
- Development requires alternative solution from above

## Remote Server Details

- **Host**: 104.238.130.1
- **SSH User**: master_jsrfyuefqf
- **MySQL Port**: 3306
- **Database**: urjxzpmdrd
- **User**: urjxzpmdrd
- **Password**: 3a7feRSwKQ (stored in `dev-config.php`)

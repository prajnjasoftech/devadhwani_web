# AWS Deployment Guide - Devadhwani

## Infrastructure Overview

### Recommended Architecture

```
                         ┌─────────────┐
                         │  Route 53   │
                         │   (DNS)     │
                         └──────┬──────┘
                                │
                         ┌──────▼──────┐
                         │   ACM SSL   │
                         └──────┬──────┘
                                │
                         ┌──────▼──────┐
                         │    EC2      │
                         │  (Laravel   │
                         │   + Vue)    │
                         └──────┬──────┘
                                │
           ┌────────────────────┼────────────────────┐
           │                    │                    │
    ┌──────▼──────┐      ┌──────▼──────┐      ┌──────▼──────┐
    │  RDS MySQL  │      │     S3      │      │ ElastiCache │
    │  (Database) │      │  (Storage)  │      │  (Redis)    │
    └─────────────┘      └─────────────┘      └─────────────┘
```

---

## Cost Estimates

### By Scale

| Temples | Users | Recommended Instance | Monthly Cost |
|---------|-------|---------------------|--------------|
| 1-5     | 50    | t3.micro (1GB)      | ~$20-25      |
| 5-20    | 200   | t3.small (2GB)      | ~$40-50      |
| 20-50   | 500   | t3.medium (4GB)     | ~$70-90      |
| 50-100  | 1000  | t3.large (8GB)      | ~$150+       |

### AWS Services Cost Breakdown

| Service | Purpose | Specs | Monthly Cost |
|---------|---------|-------|--------------|
| **EC2** | App Server | t3.micro (1GB) | ~$8 |
| **EC2** | App Server | t3.small (2GB) | ~$15 |
| **EC2** | App Server | t3.medium (4GB) | ~$30 |
| **RDS MySQL** | Database | db.t3.micro (1GB) | ~$15 |
| **RDS MySQL** | Database | db.t3.small (2GB) | ~$25 |
| **S3** | File Storage | Per GB | ~$0.023/GB |
| **Route 53** | DNS | Per hosted zone | ~$0.50 |
| **ACM** | SSL Certificate | - | Free |
| **CloudWatch** | Monitoring | Basic | ~$3 |
| **ElastiCache** | Redis Cache | cache.t3.micro | ~$12 |
| **SES** | Email Service | Per 1000 emails | ~$0.10 |
| **Data Transfer** | Outbound | Per GB | ~$0.09/GB |

### Deployment Phases

#### Phase 1: Initial (1-5 Temples) - ~$20/month
- 1x t3.micro EC2 (Application)
- 1x t3.micro EC2 (MySQL) OR MySQL on same server
- S3 for file storage
- Route 53 + ACM SSL

#### Phase 2: Growing (5-20 Temples) - ~$55/month
- 1x t3.small EC2 (Application)
- RDS MySQL db.t3.micro
- S3 for file storage
- ElastiCache Redis (optional)
- Route 53 + ACM SSL

#### Phase 3: Production (20-50 Temples) - ~$120/month
- 1x t3.medium EC2 (Application)
- RDS MySQL db.t3.small (Multi-AZ optional)
- S3 for file storage
- ElastiCache Redis
- Application Load Balancer (optional)
- Route 53 + ACM SSL

#### Phase 4: Scale (50+ Temples) - ~$250+/month
- Multiple EC2 instances behind Load Balancer
- RDS MySQL db.t3.medium (Multi-AZ)
- ElastiCache Redis cluster
- CloudFront CDN
- Auto Scaling Group

---

## EC2 Instance Comparison

| Instance | vCPU | RAM | Use Case | Price/month |
|----------|------|-----|----------|-------------|
| t3.micro | 2 | 1GB | Dev/Testing, 1-5 temples | ~$8 |
| t3.small | 2 | 2GB | Small production, 5-20 temples | ~$15 |
| t3.medium | 2 | 4GB | Medium production, 20-50 temples | ~$30 |
| t3.large | 2 | 8GB | Large production, 50-100 temples | ~$60 |

---

## Migration Guide: EC2 MySQL to RDS

### When to Migrate

Consider moving to RDS when:
- You have 10+ temples
- Need automated backups
- Want Multi-AZ for high availability
- DB management becomes overhead
- Need to scale app and DB independently

### Pre-Migration Checklist

- [ ] Note current MySQL version on EC2
- [ ] Create RDS instance with same/compatible MySQL version
- [ ] Ensure RDS security group allows EC2 app server
- [ ] Plan maintenance window (5-10 minutes downtime)
- [ ] Notify users of scheduled maintenance

### Step-by-Step Migration

#### 1. Create Database Backup on EC2

```bash
# SSH into EC2 MySQL server
ssh -i your-key.pem ubuntu@mysql-server-ip

# Create full backup
mysqldump -u root -p \
  --single-transaction \
  --routines \
  --triggers \
  --all-databases > /tmp/full_backup.sql

# Or backup specific database
mysqldump -u root -p \
  --single-transaction \
  --routines \
  --triggers \
  devadhwani > /tmp/devadhwani_backup.sql

# Check backup size
ls -lh /tmp/*.sql
```

#### 2. Create RDS Instance (AWS Console)

1. Go to **RDS** → **Create database**
2. Choose **MySQL**
3. Select **Free tier** or **Production** template
4. Settings:
   - DB instance identifier: `devadhwani-db`
   - Master username: `admin`
   - Master password: (strong password)
5. Instance configuration:
   - db.t3.micro (start small)
6. Storage:
   - 20 GB SSD (gp2)
   - Enable storage autoscaling
7. Connectivity:
   - Same VPC as EC2
   - Create new security group OR use existing
   - **NOT publicly accessible**
8. Database options:
   - Initial database name: `devadhwani`
9. Click **Create database**

Wait 5-10 minutes for RDS to be available.

#### 3. Configure Security Group

```
RDS Security Group Inbound Rules:
- Type: MySQL/Aurora
- Port: 3306
- Source: EC2 App Server Security Group (sg-xxxxx)
```

#### 4. Transfer Backup to App Server

```bash
# From MySQL EC2 server, copy to App server
scp -i your-key.pem /tmp/devadhwani_backup.sql ubuntu@app-server-ip:/tmp/

# Or download locally first, then upload to app server
```

#### 5. Import to RDS

```bash
# SSH into App Server
ssh -i your-key.pem ubuntu@app-server-ip

# Import backup to RDS
mysql -h your-rds-endpoint.rds.amazonaws.com \
  -u admin \
  -p \
  devadhwani < /tmp/devadhwani_backup.sql

# Verify import
mysql -h your-rds-endpoint.rds.amazonaws.com -u admin -p -e "USE devadhwani; SHOW TABLES;"
```

#### 6. Update Application Configuration

```bash
# Edit .env file
cd /var/www/devadhwani
nano .env
```

Update database settings:
```env
DB_CONNECTION=mysql
DB_HOST=your-rds-endpoint.rds.amazonaws.com
DB_PORT=3306
DB_DATABASE=devadhwani
DB_USERNAME=admin
DB_PASSWORD=your_rds_password
```

#### 7. Clear Cache and Restart

```bash
# Clear Laravel cache
php artisan config:clear
php artisan cache:clear

# Restart PHP-FPM
sudo systemctl restart php8.3-fpm

# Test the application
php artisan tinker
>>> DB::connection()->getPdo();
>>> App\Models\Temple::count();
```

#### 8. Verify Application

1. Open the application in browser
2. Login and verify data
3. Create a test booking
4. Check all modules work

#### 9. Cleanup (After Verification)

```bash
# Remove backup files
rm /tmp/devadhwani_backup.sql

# After 1 week of stable operation:
# - Stop EC2 MySQL server
# - After 1 month: Terminate EC2 MySQL server
```

### Rollback Plan

If issues occur, revert to EC2 MySQL:

```bash
# Edit .env
DB_HOST=ec2-mysql-private-ip

# Clear cache
php artisan config:clear
php artisan cache:clear
sudo systemctl restart php8.3-fpm
```

---

## EC2 Vertical Scaling (Changing Instance Type)

### When to Scale Up

- High CPU usage (>80% sustained)
- High memory usage (>85%)
- Slow response times
- Out of memory errors

### Steps to Change Instance Type

1. **Stop the instance** (not terminate!)
   ```
   AWS Console → EC2 → Instances → Select instance → Instance State → Stop
   ```

2. **Change instance type**
   ```
   Actions → Instance Settings → Change Instance Type → Select new type → Apply
   ```

3. **Start the instance**
   ```
   Instance State → Start
   ```

**Downtime:** ~2-3 minutes

**Note:** If using Elastic IP, the public IP remains the same.

### Recommended Upgrade Path

```
t3.micro (1GB) → t3.small (2GB) → t3.medium (4GB) → t3.large (8GB)
```

---

## Additional Costs

| Item | Cost | Notes |
|------|------|-------|
| Domain name | ~$12/year | Route 53 or external registrar |
| Prokerala API | ₹999/month | Ruby plan for Panchang data |
| SMS Gateway | Per SMS | If OTP/notifications needed |
| Email (SES) | ~$0.10/1000 | Transactional emails |

---

## Security Best Practices

### EC2 Security
- [ ] Use security groups, not open ports
- [ ] SSH only from known IPs
- [ ] Keep OS and packages updated
- [ ] Use IAM roles instead of access keys

### RDS Security
- [ ] Not publicly accessible
- [ ] Security group allows only app server
- [ ] Enable encryption at rest
- [ ] Enable automated backups

### Application Security
- [ ] APP_DEBUG=false in production
- [ ] Strong APP_KEY
- [ ] HTTPS only (ACM + ALB or Certbot)
- [ ] Regular security updates

---

## Monitoring

### CloudWatch Metrics to Monitor

- EC2: CPU, Memory, Disk, Network
- RDS: CPU, Connections, Storage
- Application: Response time, Error rate

### Recommended Alarms

- EC2 CPU > 80% for 5 minutes
- RDS Storage < 20% free
- RDS Connections > 80% of max

---

## Backup Strategy

### Database Backups

**RDS (Automated):**
- Enable automated backups
- Retention: 7-35 days
- Point-in-time recovery available

**EC2 MySQL (Manual):**
```bash
# Daily backup cron
0 2 * * * mysqldump -u root -p'password' devadhwani | gzip > /backups/devadhwani_$(date +\%Y\%m\%d).sql.gz

# Upload to S3
0 3 * * * aws s3 cp /backups/ s3://your-backup-bucket/mysql/ --recursive
```

### Application Backups

```bash
# Backup uploads and storage
aws s3 sync /var/www/devadhwani/storage/app s3://your-backup-bucket/storage/
```

---

## Quick Reference

### Useful Commands

```bash
# Check Laravel logs
tail -f /var/www/devadhwani/storage/logs/laravel.log

# Check Nginx logs
tail -f /var/log/nginx/error.log

# Check PHP-FPM status
sudo systemctl status php8.3-fpm

# Clear all Laravel cache
php artisan optimize:clear

# Check database connection
php artisan tinker
>>> DB::connection()->getPdo();
```

### Important File Locations

| File | Location |
|------|----------|
| Application | /var/www/devadhwani |
| Nginx config | /etc/nginx/sites-available/devadhwani |
| PHP config | /etc/php/8.3/fpm/php.ini |
| PHP-FPM pool | /etc/php/8.3/fpm/pool.d/www.conf |
| Laravel logs | /var/www/devadhwani/storage/logs/ |
| Nginx logs | /var/log/nginx/ |

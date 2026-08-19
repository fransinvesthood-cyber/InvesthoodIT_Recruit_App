# Candidate Opportunities Module - Quick Start Guide

## 🚀 Installation (5 minutes)

### 1. Apply Database Migration
Execute the SQL migration to create the saved opportunities table:

```bash
# Using MySQL command line
mysql -u root -p investhood_platform < database/candidate_opportunities.sql

# Or in MySQL management tool (phpMyAdmin, MySQL Workbench, etc.)
# Open database/candidate_opportunities.sql and execute
```

### 2. Verify Files
Check that all new files exist:
```
✓ models/SavedOpportunity.php
✓ controllers/CandidateOpportunitiesController.php
✓ candidate/opportunities.php
✓ candidate/opportunity_detail.php
✓ candidate/api/save_opportunity.php
✓ candidate/api/unsave_opportunity.php
✓ css/opportunities.css
✓ js/opportunities.js
✓ database/candidate_opportunities.sql
```

### 3. Clear Cache (if applicable)
If using caching, clear it:
```bash
# Browser cache - Ctrl+Shift+Delete (or Cmd+Shift+Delete on Mac)
# Server cache - if using Redis/Memcached, clear those
```

## 🎯 Testing (10 minutes)

### Test 1: Navigate to Opportunities
1. Log in as a candidate
2. Go to `/candidate/dashboard.php`
3. Click "Explore Opportunities" button
4. Should see opportunities list page

### Test 2: Search Functionality
1. In search box, type a skill name (e.g., "PHP", "Python", "Marketing")
2. Click search button or press Enter
3. Results should filter in real-time
4. Try different searches

### Test 3: Filters
1. Open "Filters" panel (collapsible on mobile)
2. Select:
   - Opportunity Type: "Internship"
   - Province: "Gauteng"
   - Work Arrangement: "Remote"
3. Click "Apply Filters"
4. Results should update
5. Try "Clear Filters"

### Test 4: Sorting
1. Change sort dropdown:
   - "Most Recent"
   - "Closing Soon"
   - "Opportunity Name"
   - "Programme"
   - "Location"
2. Results should reorder
3. Sort should persist with search/filters

### Test 5: Pagination
1. If more than 12 opportunities exist:
   - You should see pagination controls
   - Click page 2
   - Results should change
   - URL should update with page parameter

### Test 6: Save Opportunity
1. On opportunities list, click bookmark icon
2. Icon should fill with color (saved)
3. Should see notification: "Opportunity saved!"
4. Click again to unsave
5. Should see: "Opportunity removed from saved"

### Test 7: Opportunity Details
1. Click opportunity title or "View Opportunity"
2. Should see full details page with:
   - Description
   - Responsibilities
   - Skills required
   - Eligibility
   - Documents needed
3. Profile readiness card should show:
   - Completion score
   - Missing items (if any)
4. Save button in header should work

### Test 8: Responsive Design
1. On desktop - should see side filter panel
2. Resize to tablet - filters should still be accessible
3. Resize to mobile - filters should collapse, full-width cards
4. Touch/click "Apply Filters" button on mobile

## 📊 Data Verification

### Check Database
```sql
-- Verify table was created
SHOW TABLES LIKE 'candidate_saved_opportunities';

-- Verify saved opportunities
SELECT * FROM candidate_saved_opportunities;

-- Verify relationships
SELECT cso.*, o.title, u.fullname
FROM candidate_saved_opportunities cso
JOIN opportunities o ON o.id = cso.opportunity_id
JOIN users u ON u.id = cso.candidate_id;
```

### Check Opportunities Visible
```sql
-- Count published opportunities
SELECT COUNT(*) FROM opportunities WHERE status = 'published';

-- See sample opportunities
SELECT id, title, type, status FROM opportunities LIMIT 5;
```

## 🔍 Troubleshooting

### Opportunities Page Shows No Results
**Possible causes:**
- No published opportunities in database
- Application opening dates are in the future
- Application closing dates are in the past

**Solution:**
```sql
-- Check published opportunities with valid dates
SELECT * FROM opportunities 
WHERE status = 'published' 
AND (application_open_date IS NULL OR application_open_date <= CURDATE())
AND (application_close_date IS NULL OR application_close_date >= CURDATE());
```

### Save Button Not Working
**Possible causes:**
- CSRF token not present
- JavaScript error in console
- API endpoint not accessible

**Solution:**
1. Check browser console (F12) for errors
2. Verify `candidate/api/save_opportunity.php` exists
3. Check CSRF token is in meta tag:
   ```html
   <meta name="csrf-token" content="...">
   ```

### Filters Not Filtering
**Possible causes:**
- Form not submitting
- Query parameters not being passed
- Database has no data in those columns

**Solution:**
1. Check browser console for errors
2. Check URL parameters when applying filters
3. Verify opportunities have data in filter columns

### Page Looks Wrong (Mobile)
**Solution:**
1. Clear browser cache (Ctrl+Shift+Delete)
2. Do hard refresh (Ctrl+F5)
3. Check if CSS file is loading (check Network tab in DevTools)

### API Errors (Network Tab Shows Red)
**Solution:**
1. Check response status code
2. Check response body for error message
3. Verify CSRF token is being sent
4. Check server logs for PHP errors

## 📞 Support Resources

### Browser DevTools
- **Open**: F12 or Right-click → Inspect
- **Console Tab**: Shows JavaScript errors
- **Network Tab**: Shows API calls and responses
- **Elements Tab**: Shows HTML/CSS

### PHP Error Logs
Check your server's PHP error log:
```bash
# Windows XAMPP
C:\xampp\apache\logs\error.log

# Linux
/var/log/apache2/error.log

# macOS
/private/var/log/apache2/error.log
```

### MySQL Logs
```bash
# Check for connection or query errors
SHOW ENGINE INNODB STATUS;
```

## ✅ Verification Checklist

Before going live, ensure:

- [ ] Database migration applied successfully
- [ ] All new files are present and accessible
- [ ] Search functionality works
- [ ] Filters work correctly
- [ ] Sorting displays options
- [ ] Pagination works (if applicable)
- [ ] Save/unsave works via AJAX
- [ ] Opportunity details page displays correctly
- [ ] Profile readiness indicator shows
- [ ] Responsive design works on mobile
- [ ] No console errors when using features
- [ ] Dashboard links work
- [ ] CSRF tokens are being sent with API requests

## 🎓 Feature Walkthrough

### For End Users (Candidates)

**First Time:**
1. Log into dashboard
2. Click "Explore Opportunities" button
3. Browse opportunities cards
4. Search or filter to find relevant roles
5. Click opportunity title to see details
6. Review requirements
7. Check profile readiness
8. Save opportunity for later
9. Return to dashboard

**Regular Use:**
- Visit `/candidate/opportunities.php` regularly
- Check for new opportunities
- Apply filters for relevant roles
- Save interesting opportunities
- Update profile to improve readiness
- Prepare to apply when application module is ready

### For Administrators

**Managing Opportunities:**
1. Go to Admin Dashboard → Opportunities
2. Create new opportunities with:
   - Title, description, responsibilities
   - Skills required
   - Eligibility requirements
   - Required documents
3. Set application dates
4. Publish opportunity
5. Opportunity appears to candidates

**Monitoring:**
- See which opportunities are getting views/saves
- Track applications (when app module is live)
- Close opportunities when positions filled

## 🚢 Production Deployment

### Pre-deployment
- [ ] Test all features thoroughly
- [ ] Backup database
- [ ] Test on staging environment
- [ ] Verify all permissions are correct
- [ ] Test CSRF token generation
- [ ] Test with various user roles

### Deployment
- [ ] Apply database migration
- [ ] Upload new files
- [ ] Verify file permissions (755 for dirs, 644 for files)
- [ ] Clear application cache
- [ ] Test in production
- [ ] Monitor error logs

### Post-deployment
- [ ] Monitor database performance
- [ ] Check error logs daily
- [ ] Gather user feedback
- [ ] Plan next features

---

**Module Version**: 1.0.0  
**Status**: ✅ Production Ready  
**Last Updated**: 2026-08-13

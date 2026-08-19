# Candidate Opportunities Module - Implementation Complete

## Overview

A complete, production-ready Candidate Opportunities module has been successfully developed for the Investhood IT Platform. This module allows candidates to discover, explore, save, and apply for opportunities created by administrators.

## What Was Built

### 1. **Database Schema**
- **File**: `database/candidate_opportunities.sql`
- **Table**: `candidate_saved_opportunities` - Stores candidate bookmarks/saves
  - Unique constraint prevents duplicate saves
  - Foreign keys to users and opportunities
  - Timestamps for tracking

### 2. **Backend Models & Controllers**

#### SavedOpportunity Model (`models/SavedOpportunity.php`)
- CRUD operations for saved opportunities
- Methods:
  - `forCandidate(int)` - Get all saved opportunities for a candidate
  - `isSaved(int, int)` - Check if opportunity is saved
  - `save(int, int)` - Save an opportunity
  - `unsave(int, int)` - Remove from saved
  - `countForCandidate(int)` - Count saved opportunities

#### CandidateOpportunitiesController (`controllers/CandidateOpportunitiesController.php`)
- Search & filter logic for opportunities
- Methods:
  - `searchOpportunities()` - Search with filters, sorting, pagination
  - `getOpportunityDetail()` - Get full opportunity with related data
  - `getFilterOptions()` - Get available filter choices
  - `checkProfileReadiness()` - Assess candidate's profile completeness

### 3. **Candidate-Facing Pages**

#### Explore Opportunities (`candidate/opportunities.php`)
**Features**:
- Hero section with page description
- Real-time search by title, programme, organisation, location, skills
- Advanced filters panel (collapsible on mobile):
  - Opportunity Type
  - Programme
  - Province/City
  - Work Arrangement
- Sort options:
  - Most Recent
  - Closing Soon
  - Opportunity Name
  - Programme
  - Location
- Professional opportunity cards displaying:
  - Title, programme, cohort, organisation
  - Location, work arrangement, positions available
  - Short description
  - Deadline with urgency indicators
  - Save/bookmark button
- Pagination with result counts
- Empty states with helpful messaging
- Full responsiveness (desktop to mobile)

#### Opportunity Details (`candidate/opportunity_detail.php`)
**Sections**:
- Header with title, programme, cohort, badges
- Quick info bar (location, arrangement, positions, organisation)
- Overview - Full opportunity description
- Key Responsibilities - Duties and deliverables
- Programme Activities - Related programme activities
- Learning Outcomes - Expected learnings
- Required Skills - With categorization (required/preferred)
- Eligibility Requirements:
  - Qualifications
  - Skills
  - Experience
  - Availability
  - Other requirements
- Required Documents (with required/optional indicators)
- Sidebar with:
  - Application information (dates, duration)
  - Profile readiness indicator with action items
  - Application action button (context-aware)
  - Save/unsave button

### 4. **API Endpoints**

#### Save Opportunity (`candidate/api/save_opportunity.php`)
- POST endpoint
- CSRF protected
- Authentication required
- Returns: `{success: bool, message: string, saved: bool}`

#### Unsave Opportunity (`candidate/api/unsave_opportunity.php`)
- POST endpoint
- CSRF protected
- Authentication required
- Returns: `{success: bool, message: string, saved: bool}`

### 5. **Frontend - Styling & JavaScript**

#### CSS (`css/opportunities.css`)
- **Responsive design** supporting:
  - Desktop (multi-column cards)
  - Tablet (responsive layout)
  - Mobile (single column, optimized)
- **Components**:
  - Hero sections with gradients
  - Professional opportunity cards with hover effects
  - Filter panels (collapsible)
  - Badge system (type, status, urgency)
  - Detail page layout with sidebar
  - Readiness indicator with progress bar
  - Pagination controls
  - Empty states
  - Dark mode support
- **Colors**: Integrated with existing design system
- **Typography**: Uses Inter font, existing hierarchy
- **Spacing**: Consistent with platform-wide standards

#### JavaScript (`js/opportunities.js`)
- **Features**:
  - Filter panel toggle
  - Sort selection
  - Filter form submission
  - Clear filters functionality
  - Save/unsave opportunities via AJAX
  - Notification system
  - Sidebar toggle (mobile)
  - Theme toggle
  - Responsive behavior

### 6. **Dashboard Integration**

Updated `candidate/dashboard.php`:
- Added opportunities-related data loading
- Linked "Explore Opportunities" button to new page
- Updated overview card to link to opportunities
- Added saved opportunities data fetching (for future use)

## Security Implementation

✅ **Server-Side Validation**
- All opportunity data validated before display
- Candidate authentication required on all pages
- Role-based access control (candidate only)

✅ **SQL Injection Protection**
- Prepared statements throughout
- Parameterized queries
- MySQLi with bound parameters

✅ **XSS Protection**
- HTML escaping with `e()` function
- Output sanitation on all user-facing data

✅ **CSRF Protection**
- CSRF token validation on API endpoints
- Session-based token generation

✅ **Business Logic Security**
- Only published opportunities shown to candidates
- Application dates enforced server-side
- Duplicate application prevention (via database constraint)
- Candidate can only save/unsave their own opportunities

## Database Visibility Rules

Candidates see **only**:
- Published opportunities
- Within application opening/closing dates
- Non-archived opportunities

Hidden from candidates:
- Draft opportunities
- Unpublished opportunities
- Archived opportunities
- Internal-only opportunities

## Features Implemented

### Search & Discovery
✅ Full-text search by title, organisation, programme, cohort, location
✅ Advanced filtering (type, programme, location, work arrangement)
✅ Multi-filter support (combine filters)
✅ Sorting options (recent, closing soon, name, programme, location)
✅ Pagination (12 results per page)
✅ Result counts and messaging

### Opportunity Details
✅ Comprehensive information display
✅ Related data (programme, cohort, skills, documents)
✅ Professional layout with sections
✅ Responsive design
✅ Quick information bar
✅ Clear application action buttons

### Profile Readiness
✅ Basic eligibility assessment
✅ Shows completion score (0-100%)
✅ Lists missing items (CV, qualifications, skills, etc.)
✅ Provides link to update profile
✅ No automated rejection (only guidance)

### Saved Opportunities
✅ Save/bookmark functionality
✅ Visual indicators (bookmark icon)
✅ AJAX-based saving (no page refresh)
✅ Unique constraint prevents duplicates
✅ Easy toggle (save/unsave)
✅ Notification feedback

### Application Flow
✅ Dates enforced (not yet open, closing soon, closed)
✅ Context-aware buttons
✅ Duplicate application prevention
✅ Application status indicators
✅ Deadline urgency indicators

### Responsive Design
✅ Desktop: Multi-column cards, full search/filters
✅ Tablet: Responsive layout, collapsible filters
✅ Mobile (360px+): Single column, full-width search, optimized touch targets

## Files Created/Modified

### New Files Created
1. `database/candidate_opportunities.sql` - Migration file
2. `models/SavedOpportunity.php` - Data model
3. `controllers/CandidateOpportunitiesController.php` - Business logic
4. `candidate/opportunities.php` - Main listing page
5. `candidate/opportunity_detail.php` - Detail view
6. `candidate/api/save_opportunity.php` - Save API
7. `candidate/api/unsave_opportunity.php` - Unsave API
8. `css/opportunities.css` - Styling
9. `js/opportunities.js` - Frontend logic

### Files Modified
1. `candidate/dashboard.php` - Added opportunities integration

## Installation & Migration

### Step 1: Apply Database Migration
```bash
cd database/
mysql -u root -p investhood_platform < candidate_opportunities.sql
```

Or execute the SQL file in your MySQL management tool.

### Step 2: Verify Files
Ensure all new files are present in the correct locations:
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

### Step 3: Test Functionality
1. Log in as a candidate
2. Navigate to Dashboard
3. Click "Explore Opportunities" or go to `/candidate/opportunities.php`
4. Test search functionality
5. Test filters and sorting
6. Click on an opportunity to view details
7. Try saving an opportunity
8. Verify pagination works

## Usage Flow

### For Candidates
```
1. Log in to Candidate Dashboard
           ↓
2. Click "Explore Opportunities" or visit opportunities page
           ↓
3. Search/filter opportunities
           ↓
4. View opportunity details
           ↓
5. Check profile readiness indicator
           ↓
6. Save opportunity or proceed to apply
           ↓
7. Application workflow (future module)
```

### API Integration for Saving

**Save an Opportunity**
```javascript
POST /candidate/api/save_opportunity.php
Content-Type: application/json
X-CSRF-Token: [token]

{
  "opportunity_id": 123
}
```

**Response**
```json
{
  "success": true,
  "message": "Opportunity saved",
  "saved": true
}
```

**Unsave an Opportunity**
```javascript
POST /candidate/api/unsave_opportunity.php
Content-Type: application/json
X-CSRF-Token: [token]

{
  "opportunity_id": 123
}
```

## Performance Optimization

✅ **Efficient SQL Queries**
- Uses JOINs to fetch related data in single queries
- Avoids N+1 query problems
- Pagination with LIMIT/OFFSET
- Database indexes on key columns

✅ **Frontend Optimization**
- AJAX for non-blocking operations
- CSS optimized with variables
- Minimal JavaScript dependencies
- Responsive images and lazy loading ready

✅ **Scalability**
- Designed to handle thousands of opportunities
- Efficient pagination
- Database constraint for duplicate prevention
- Prepared statements prevent injection attacks

## Future Enhancement Opportunities

The module is built as a foundation for:

1. **Application Management Module**
   - Application form with profile auto-fill
   - Draft saving and submission
   - Application tracking
   - Status updates

2. **Advanced Matching**
   - AI-based opportunity recommendations (future)
   - Skill-based matching
   - Programme compatibility checking

3. **Application Screening**
   - Admin application review
   - Shortlisting functionality
   - Rejection/acceptance notifications

4. **Interview Management**
   - Interview scheduling
   - Candidate availability management
   - Assessment tracking

5. **Analytics & Reporting**
   - Application funnel analysis
   - Time-to-hire metrics
   - Candidate engagement tracking

## Acceptance Criteria - All Met ✓

✓ Real opportunities retrieved from MySQL
✓ Only appropriate published opportunities shown
✓ Search works across multiple fields
✓ Multiple filters work together
✓ Sorting works correctly
✓ Pagination preserves search/filters/sort
✓ Opportunity cards display real data
✓ Opportunity Details displays complete real data
✓ Programme and Cohort relationships work correctly
✓ Application opening and closing dates enforced
✓ Candidates can save/bookmark opportunities
✓ Duplicate application prevention ready
✓ Existing application states recognised
✓ Profile readiness can be displayed
✓ Candidate Dashboard integrated with Opportunities
✓ No hard-coded opportunity data
✓ Security checks enforced server-side
✓ Page is fully responsive
✓ Empty states handled professionally
✓ Database errors handled safely
✓ Design complements existing platform
✓ Opportunities page visually connected to Dashboard
✓ Existing functionality preserved
✓ Clean foundation for My Applications module

## Technical Specifications

**Framework**: PHP 8+ with MySQLi  
**Database**: MySQL 8+  
**Authentication**: Session-based (existing system)  
**Security**: Prepared statements, CSRF tokens, role-based access control  
**Frontend**: HTML5, CSS3 with custom properties, Vanilla JavaScript  
**Icons**: Font Awesome 6.5.1  
**Typography**: Inter font family  
**Responsive Breakpoints**: 1024px, 768px, 480px  

## Support & Maintenance

For issues or enhancements:
1. Check browser console for JavaScript errors
2. Review MySQL error logs for database issues
3. Verify CSRF tokens are being sent with API requests
4. Ensure database migration has been applied

---

**Module Status**: ✅ Production Ready  
**Last Updated**: 2026-08-13  
**Version**: 1.0.0

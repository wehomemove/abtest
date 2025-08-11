# Publishing the A/B Testing Package

## Prerequisites

1. **Packagist Account**: Create an account at https://packagist.org if you don't have one
2. **GitHub Repository**: Ensure the package is pushed to GitHub at `wehomemove/abtest`
3. **Composer installed locally**

## Steps to Update and Publish

### 1. Make Your Changes

Update your code as needed in the package.

### 2. Update Version (if needed)

If you need to bump the version in any documentation:
```bash
# Update version references in README.md if needed
```

### 3. Commit Your Changes

```bash
git add .
git commit -m "Your descriptive commit message"
git push origin main
```

### 4. Create a New Version Tag

```bash
# For a patch release (bug fixes): 1.2.0 -> 1.2.1
git tag v1.2.1

# For a minor release (new features, backwards compatible): 1.2.0 -> 1.3.0
git tag v1.3.0

# For a major release (breaking changes): 1.2.0 -> 2.0.0
git tag v2.0.0

# Push the tag to GitHub
git push origin --tags
```

### 5. Initial Package Submission (First Time Only)

If this is your first time publishing to Packagist:

1. Go to https://packagist.org
2. Click "Submit"
3. Enter your GitHub repository URL: `https://github.com/wehomemove/abtest`
4. Click "Check" and then "Submit"

### 6. Auto-Update Setup (Recommended)

Set up automatic updates from GitHub:

1. Go to your package page on Packagist
2. Click "Settings" 
3. Under "GitHub Service Hook", click "Update"
4. This will create a webhook so Packagist updates automatically when you push tags

### 7. Manual Update (If Auto-Update Not Set)

If you haven't set up auto-update:

1. Go to https://packagist.org/packages/wehomemove/abtest
2. Click "Update" button
3. Packagist will fetch the latest tags from GitHub

## Testing Before Publishing

### Local Testing

Test your package locally before publishing:

```bash
# In a test Laravel project
composer config repositories.abtest path /path/to/your/abtest/package
composer require wehomemove/abtest:@dev
```

### Run Tests

```bash
composer test
```

## Version Guidelines

- **Patch Release (1.2.x)**: Bug fixes, documentation updates
- **Minor Release (1.x.0)**: New features, backwards compatible
- **Major Release (x.0.0)**: Breaking changes, major rewrites

## Quick Commands Reference

```bash
# View current tags
git tag -l

# Create and push a new tag
git tag v1.2.1
git push origin v1.2.1

# Delete a tag (if needed)
git tag -d v1.2.1
git push origin --delete v1.2.1

# Test package locally
composer test

# Check composer.json validity
composer validate
```

## Troubleshooting

### Package Not Updating on Packagist

1. Check if webhook is configured correctly
2. Manually click "Update" on Packagist
3. Ensure tags are pushed to GitHub: `git push origin --tags`

### Composer Can't Find Package

1. Wait a few minutes for Packagist to index
2. Clear Composer cache: `composer clear-cache`
3. Try: `composer require wehomemove/abtest:dev-main` for latest development version

## Important Notes

- Always test changes locally before tagging
- Follow semantic versioning
- Update README.md if API changes
- Consider backwards compatibility
- Document breaking changes in release notes
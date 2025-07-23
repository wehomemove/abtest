/**
 * A/B Testing JavaScript Helper
 * Simple function to track A/B test events
 */
window.abtrack = function(experiment, event, properties = {}) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    return fetch('/api/ab-testing/track', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken })
        },
        body: JSON.stringify({
            experiment: experiment,
            event: event,
            properties: properties
        })
    }).catch(error => {
        console.error('A/B test tracking error:', error);
    });
};

/**
 * Get current variant for an experiment
 */
window.abvariant = function(experiment, userId = null) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    return fetch('/api/ab-testing/variant', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken })
        },
        body: JSON.stringify({
            experiment: experiment,
            user_id: userId
        })
    })
    .then(response => response.json())
    .then(data => data.variant)
    .catch(error => {
        console.error('A/B test variant error:', error);
        return 'control'; // fallback
    });
};
(function() {
    console.log('StepFox AI Test Block: Loading...');
    
    if (!wp || !wp.blocks) {
        console.error('WordPress blocks not available');
        return;
    }
    
    var registerBlockType = wp.blocks.registerBlockType;
    var el = wp.element.createElement;
    
    registerBlockType('stepfox-ai/test-block', {
        title: 'StepFox AI Test',
        icon: 'smiley',
        category: 'widgets',
        edit: function() {
            return el('p', {}, 'StepFox AI Test Block - Edit Mode');
        },
        save: function() {
            return el('p', {}, 'StepFox AI Test Block - Saved');
        }
    });
    
    console.log('StepFox AI Test Block: Registered!');
})();

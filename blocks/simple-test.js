console.log('StepFox AI Simple Test: Script loaded at', new Date().toISOString());
console.log('StepFox AI Simple Test: wp object exists?', typeof wp !== 'undefined');
console.log('StepFox AI Simple Test: wp.blocks exists?', typeof wp !== 'undefined' && typeof wp.blocks !== 'undefined');

if (typeof wp !== 'undefined' && wp.domReady) {
    wp.domReady(function() {
        console.log('StepFox AI Simple Test: DOM ready, registering block...');
        
        if (wp.blocks && wp.blocks.registerBlockType && wp.element) {
            wp.blocks.registerBlockType('stepfox-ai/simple-test', {
                title: 'StepFox Test Block',
                icon: 'smiley',
                category: 'common',
                edit: function() {
                    return wp.element.createElement('p', null, 'StepFox AI Test Block Works!');
                },
                save: function() {
                    return wp.element.createElement('p', null, 'StepFox AI Test Block Works!');
                }
            });
            
            console.log('StepFox AI Simple Test: Block registered successfully!');
        } else {
            console.error('StepFox AI Simple Test: Missing required wp packages');
        }
    });
} else {
    console.error('StepFox AI Simple Test: wp or wp.domReady not available');
}

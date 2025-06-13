import { registerCheckoutBlock } from '@woocommerce/blocks-checkout';
import { FillBlock } from './block';
import metadata from './block.json';

const blockLocation = window.postnlSettings?.blockLocation || 'woocommerce/checkout-shipping-address-block';

registerCheckoutBlock({
    metadata: {
        ...metadata,
        parent: [blockLocation], // Dynamically set the parent
    },
    component: FillBlock,
});

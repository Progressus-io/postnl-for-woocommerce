// frontend.js (e.g., build/postnl-fill-in-with-frontend.js)
import { registerCheckoutBlock } from '@woocommerce/blocks-checkout';
import { FillBlock } from './block';
import metadata from './block.json';

registerCheckoutBlock({
	metadata,
	component: FillBlock,
});

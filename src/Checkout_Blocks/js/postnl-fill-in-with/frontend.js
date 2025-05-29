import { registerCheckoutBlock } from '@woocommerce/blocks-checkout';
import { FillBlock } from './block';
import metadata from './block.json';

registerCheckoutBlock({
	metadata,
	component: FillBlock,
});

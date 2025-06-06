# -*- coding: utf-8 -*-

# PLEASE DO NOT EDIT THIS FILE, IT IS GENERATED AND WILL BE OVERWRITTEN:
# https://github.com/ccxt/ccxt/blob/master/CONTRIBUTING.md#how-to-contribute-code

from ccxt.async_support.hitbtc import hitbtc
from ccxt.abstract.bequant import ImplicitAPI
from ccxt.base.types import Any


class bequant(hitbtc, ImplicitAPI):

    def describe(self) -> Any:
        return self.deep_extend(super(bequant, self).describe(), {
            'id': 'bequant',
            'name': 'Bequant',
            'pro': True,
            'countries': ['MT'],  # Malta
            'urls': {
                'logo': 'https://github.com/user-attachments/assets/0583ef1f-29fe-4b7c-8189-63565a0e2867',
                'api': {
                    # v3
                    'public': 'https://api.bequant.io/api/3',
                    'private': 'https://api.bequant.io/api/3',
                },
                'www': 'https://bequant.io',
                'doc': [
                    'https://api.bequant.io/',
                ],
                'fees': [
                    'https://bequant.io/fees-and-limits',
                ],
                'referral': 'https://bequant.io/referral/dd104e3bee7634ec',
            },
        })

(function () {
    'use strict';

    const isFilipino = document.documentElement.dataset.sellerLanguage === 'fil';

    const translations = {
        'Dashboard': 'Dashboard',
        'My Submissions': 'Submissions ko',
        'Submission Details': 'Submission details',
        'Offers': 'Offers',
        'Trade History': 'Trade History',
        'Profile': 'Profile',
        'Transactions': 'Transactions',
        'Transaction Details': 'Transaction details',
        'Settings': 'Settings',
        'Appearance': 'Appearance',
        'Choose how the Seller portal looks. This setting is saved in this browser.': 'Piliin kung paano itsura ng Seller portal. Dito sa browser mase-save yung setting.',
        'Theme': 'Theme',
        'Light mode': 'Light mode',
        'Dark mode': 'Dark mode',
        'SELLER PORTAL': 'SELLER PORTAL',
        'Logout': 'Mag-logout',
        'Log out': 'Mag-logout',
        'Dashboard overview': 'Buod ng dashboard',
        'VNZ TECH OVERVIEW': 'BUOD NG VNZ TECH',
        'Monitor submissions, offers, and transactions from one place.': 'Dito mo makikita at ma-track lahat ng submissions, offers, at transactions mo.',
        'PENDING REVIEW': 'PENDING FOR REVIEW',
        'PENDING OFFERS': 'PENDING OFFERS',
        'ACCEPTED': 'ACCEPTED',
        'All seller submissions': 'Lahat ng submissions mo',
        'Waiting for admin review': 'Naghihintay ma-review ng admin',
        'Offers awaiting action': 'Offers na kailangan mong sagutin',
        'Accepted submissions': 'Accepted na submissions',
        'Recent Activity': 'Recent activity',
        'Latest seller submissions and admin actions.': 'Dito makikita yung latest submissions at updates.',
        'No activity yet': 'Wala pang updates',
        'New seller submissions and admin actions will appear here.': 'Dito lalabas yung updates tungkol sa submissions mo.',
        'System Status': 'System status',
        'Current platform status.': 'Status ng platform ngayon.',
        'Database': 'Database',
        'Connected': 'Konektado',
        'Authentication': 'Login',
        'Verified admin': 'Admin verified',
        'Admin Session': 'Session ng Admin',
        'Active': 'Aktibo',
        'Not active': 'Hindi aktibo',
        'My submissions.': 'Submissions ko.',
        'Track every phone and board you\'ve submitted to VNZ TECH.': 'Dito mo ma-track lahat ng phone at board na sinubmit mo sa VNZ TECH.',
        '+ SELL AN ITEM': '+ MAGBENTA NG ITEM',
        'SELL AN ITEM': 'MAGBENTA NG ITEM',
        'All Statuses': 'Lahat ng status',
        'All Item Types': 'Lahat ng item type',
        'Search reference, seller, or mobile...': 'I-search ang reference, seller, o mobile...',
        'Search by reference or item': 'I-search ang reference o item',
        'Search': 'Search',
        'CLEAR': 'CLEAR',
        'Showing': 'Pinapakita',
        'Previous': 'Previous',
        'Next': 'Next',
        'VIEW ALL': 'TINGNAN LAHAT',
        'VIEW SUBMISSION': 'VIEW SUBMISSION',
        'VIEW DETAILS': 'VIEW DETAILS',
        'Your negotiations': 'Mga alok at usapan',
        'Each card shows the latest offer. Open a submission to see its full history and available actions.': 'Makikita mo rito yung latest offer. Buksan ang submission para makita yung buong history at puwede mong gawin.',
        'All': 'Lahat',
        'Needs your response': 'Kailangan ng reply mo',
        'Waiting for VNZ TECH': 'Waiting sa VNZ TECH',
        'Closed': 'Closed',
        'Under review': 'Under review',
        'No offers yet': 'Wala ka pang offers',
        'When VNZ TECH reviews one of your submissions and sends an offer, it will appear here.': 'Lalabas dito kapag may offer na ang VNZ TECH sa submission mo.',
        'Go to my submissions': 'Pumunta sa submissions ko',
        'No matching offers': 'Walang offer na nakita',
        'Try a different reference or item name.': 'Try mo ibang reference o item name.',
        'HISTORY': 'TRANSACTIONS',
        'Recent transactions': 'Recent transactions mo',
        'No transactions yet.': 'Wala ka pang transactions.',
        'Completed transactions will appear here.': 'Dito lalabas yung completed transactions mo.',
        'Pending': 'Pending',
        'Completed': 'Completed',
        'Cancelled': 'Cancelled',
        'My Trade Requests': 'Trade requests ko',
        'Track the status and requirements for the items you have requested.': 'Dito mo makikita ang status at requirements ng mga item na ni-request mo.',
        'Trade': 'Trade',
        'REQUESTS': 'REQUESTS',
        'Your submitted trade requests will appear here once you request an item.': 'Dito lalabas ang trade request mo kapag nag-request ka ng item.',
        'Required Trade Items': 'Trade requirements',
        'Condition:': 'Condition:',
        'Requested:': 'Requested:',
        'Reviewed:': 'Reviewed:',
        'VNZ TECH Notes': 'Notes ng VNZ TECH',
        'ACCOUNT': 'ACCOUNT',
        'Manage your account security and notification preferences.': 'Dito mo maa-update yung security at notifications ng account mo.',
        'Language': 'Wika',
        'Choose the language for supported Seller portal labels. Your choice is saved to your account.': 'Pili ka ng language para sa Seller portal. Automatic itong mase-save sa account mo.',
        'Preferred Language': 'Language',
        'English': 'English',
        'Filipino': 'Filipino',
        'SAVE LANGUAGE': 'SAVE LANGUAGE',
        'SAVING...': 'Sine-save...',
        'Language preference saved.': 'Na-save na yung language mo.',
        'Email Address': 'Email Address',
        'Your email is used for account-related communication.': 'Ginagamit ang email mo para sa mga mensaheng tungkol sa account.',
        'Verified': 'Verified',
        'Not verified': 'Not verified',
        'This email address has not been verified. The verification link expires after 30 minutes and can only be used once.': 'Hindi pa verified yung email mo. Mag-e-expire yung link after 30 minutes at isang beses lang siya magagamit.',
        'SEND VERIFICATION EMAIL': 'SEND VERIFICATION EMAIL',
        'Two-Factor Authentication': 'Two-Factor Authentication',
        'Authenticator enabled': 'Naka-enable ang authenticator',
        'Authenticator not set up': 'Hindi pa naka-set up',
        'Authenticator code at every login': 'Authenticator code sa bawat pag-login',
        'Yes, require the 6-digit code': 'Oo, hingin yung 6-digit code',
        'No, use password-only login': 'Hindi, password lang sa login',
        'SAVE LOGIN PREFERENCE': 'SAVE LOGIN SETTING',
        'Set up authenticator': 'I-set up ang authenticator',
        'Change Password': 'Palitan ang Password',
        'Current Password': 'Current password',
        'New Password': 'New password',
        'Confirm New Password': 'Confirm new password',
        'At least 8 characters.': 'Dapat at least 8 characters.',
        'UPDATE PASSWORD': 'UPDATE PASSWORD',
        'Notification Preferences': 'Notification settings',
        'Submission updates': 'Mga update sa submission',
        'Offer updates': 'Mga update sa alok',
        'Transaction updates': 'Mga update sa transaksyon',
        'SAVE PREFERENCES': 'SAVE PREFERENCES',
        'Save Changes': 'Save changes',
        'Back to Dashboard': 'Balik sa dashboard',
        'VIEW NEGOTIATION': 'VIEW OFFER DETAILS',
        'REVIEW & RESPOND': 'REVIEW & REPLY',
        'OFFER FROM VNZ TECH': 'OFFER FROM VNZ TECH',
        'YOUR COUNTER-OFFER': 'COUNTER-OFFER MO',
        'MESSAGE FROM VNZ TECH': 'MESSAGE FROM VNZ TECH',
        'YOUR MESSAGE': 'MESSAGE MO',
        'Latest offer': 'Pinakabagong alok',
        'No offer yet': 'Wala pang alok',
        'No submissions yet': 'Wala pang submission',
        'Profile updated successfully.': 'Updated na yung profile mo.',
        'Please try again later.': 'Subukan muli mamaya.',
        'Unable to load settings': 'Hindi ma-load ang settings',
        'Unable to load offers': 'Hindi ma-load ang offers',
        'Unable to load transactions': 'Hindi ma-load ang transactions',
        'Welcome back,': 'Welcome ulit,',
        'Track your submissions, offers, and completed transactions from one place.': 'Dito mo ma-track lahat ng submissions, offers, at completed transactions mo.',
        'Your account': 'Account mo',
        'Full Name': 'Full name',
        'FULL NAME': 'PANGALAN',
        'Email': 'Email',
        'EMAIL': 'EMAIL',
        'Mobile': 'Mobile',
        'MOBILE': 'MOBILE',
        'Not provided': 'Walang nilagay',
        'Member Since': 'Member since',
        'MEMBER SINCE': 'MEMBER SIMULA NOONG',
        'READY TO SELL?': 'READY KA NA BANG MAGBENTA?',
        'Submit another device.': 'Mag-submit pa ng device.',
        'Tell us about your phone, device, or scrap board and our team will review it.': 'Ilagay yung details ng phone, device, o scrap board mo para ma-review ng team namin.',
        'SELL TO VNZ TECH': 'MAGBENTA SA VNZ TECH',
        'TOTAL SUBMISSIONS': 'TOTAL NA SUBMISSIONS',
        'ACTIVE OFFERS': 'MAY MGA OFFER',
        'COMPLETED': 'TAPOS NA',
        'TOTAL PAID': 'TOTAL NA BAYAD',
        'All submitted devices': 'Lahat ng sinubmit mong device',
        'Completed transactions': 'Completed transactions',
        'From completed transactions': 'Galing sa completed transactions',
        'ACTIVITY': 'UPDATES',
        'Recent submissions': 'Recent submissions mo',
        'No submissions yet.': 'Wala ka pang submissions.',
        'Your submitted devices will appear here once you make your first submission.': 'Lalabas dito yung mga sinubmit mong device.',
        'SUBMIT A DEVICE': 'MAG-SUBMIT NG DEVICE',
        'items': 'items',
        'Transactions.': 'Transactions.',
        'Track your accepted offers and transaction progress with VNZ TECH.': 'Dito mo ma-track yung accepted offers at transaction status mo sa VNZ TECH.',
        'Search and filter your transactions.': 'Mag-search o mag-filter ng transactions mo.',
        'QUICK RANGE': 'QUICK RANGE',
        'TODAY': 'TODAY',
        'THIS WEEK': 'THIS WEEK',
        'THIS MONTH': 'THIS MONTH',
        'LAST MONTH': 'LAST MONTH',
        'CUSTOM': 'CUSTOM',
        'SEARCH': 'SEARCH',
        'STATUS': 'STATUS',
        'FROM': 'FROM',
        'TO': 'TO',
        'APPLY': 'APPLY',
        'NO TRANSACTIONS FOUND': 'WALANG TRANSACTIONS',
        'Your accepted offers will appear here': 'Lalabas dito yung accepted offers mo',
        'Session expired': 'Nag-expire yung session mo',
        'Invalid session': 'Invalid yung session mo',
        'Please log in again.': 'Mag-log in ulit.',
        'User account not found': 'Hindi mahanap yung account',
        'Please contact VNZ TECH.': 'I-contact ang VNZ TECH.',
        'Account inactive': 'Inactive ang account',
        'Your account is currently inactive.': 'Inactive ngayon yung account mo.',
        'Account unavailable': 'Hindi available ang account',
        'Your account session is no longer available.': 'Hindi na available yung account session mo.',
        'Unable to load dashboard': 'Hindi ma-load ang dashboard',
        'Unable to load profile': 'Hindi ma-load ang profile',
        'Unable to load transaction': 'Hindi ma-load ang transaction',
        'Please close this window and try again.': 'Isara yung window at subukan ulit.',
        'New Submission': 'New submission',
        'Under Review': 'Under review',
        'Offer Pending': 'Offer pending',
        'Offer Sent': 'Offer sent',
        'Declined': 'Declined',
        'New': 'New',
        'Select a status to filter your submissions.': 'Pumili ng status para ma-filter yung submissions mo.',
        'SUBMISSIONS': 'SUBMISSIONS',
        'No submissions found': 'Walang submissions',
        'SELL YOUR FIRST ITEM': 'MAGBENTA NG FIRST ITEM MO',
        'Items': 'Items',
        'ITEMS': 'ITEMS',
        'ASKING PRICE': 'ASKING PRICE',
        'LATEST OFFER': 'LATEST OFFER',
        'OFFER': 'OFFER',
        'NO OFFERS FOUND': 'WALANG OFFERS',
        'Offers from VNZ TECH': 'Offers from VNZ TECH',
        'Your counter-offers': 'Counter-offers mo',
        'Accepted, declined, or completed': 'Accepted, declined, o completed',
        'Submissions with an offer': 'Submissions na may offer',
        'No trade requests yet': 'Wala ka pang trade requests',
        'Your Profile': 'Profile mo',
        'Manage the information connected to your VNZ TECH seller account.': 'Dito mo maa-update yung info ng seller account mo sa VNZ TECH.',
        'Personal Information': 'Personal info',
        'Your email is tied to your account.': 'Naka-link sa account mo yung email na ’to.',
        'Mobile Number': 'Mobile number',
        'Address': 'Address',
        'Enter your complete address': 'Ilagay yung complete address mo',
        'Change Profile Picture': 'Palitan yung profile picture',
        'SAVE CHANGES': 'SAVE CHANGES',
        'Settings could not be loaded. Please try again later.': 'Hindi ma-load ang settings. Subukan ulit mamaya.',
        'Email Notifications': 'Email notifications',
        'Save which updates you want by email. Email updates require a verified email address and configured mail delivery. In-app updates will continue to appear in your seller portal.': 'Piliin kung anong updates ang gusto mong matanggap sa email. Kailangan verified ang email mo. Makikita mo pa rin sa Seller portal yung in-app updates.',
        'When a submission is reviewed or its status changes.': 'Kapag na-review yung submission mo o nagbago yung status nito.',
        'When you receive an offer or a response to a counter-offer.': 'Kapag may offer o reply sa counter-offer mo.',
        'When a transaction or payment status changes.': 'Kapag nagbago yung status ng transaction o payment.',
        'Authenticator is set up and can be used for account recovery. Requiring its code at every login is optional.': 'Naka-set up na yung authenticator mo at puwede ito para ma-recover yung account. Optional kung hihingin yung code kada login.',
        'Set up an authenticator app for account recovery. Requiring its code at every login is a separate choice.': 'Mag-set up ng authenticator app para may pang-recover ka ng account. Ikaw ang pipili kung kailangan yung code kada login.',
        'Confirm your current password': 'Ilagay ulit yung current password mo',
        'Current password': 'Current password',
        'Authenticator code': 'Authenticator code',
        'DISABLE 2FA': 'I-DISABLE ANG 2FA',
        'SET UP AUTHENTICATOR': 'SET UP AUTHENTICATOR',
        'VERIFY AND ENABLE': 'VERIFY AND ENABLE',
        'Can\'t scan? Enter the setup key manually': 'Hindi ma-scan? I-type na lang yung setup key',
        'Scan this QR code with your authenticator app. Then enter its current 6-digit code to enable two-factor authentication.': 'I-scan itong QR code gamit yung authenticator app mo. Ilagay yung 6-digit code para ma-enable ang 2FA.',
        'Choose an item to trade': 'Pumili ng item para i-trade',
        'No trade items are currently available.': 'Walang available na trade items ngayon.',
        'Request Trade': 'Mag-request ng trade',
        'Unavailable': 'Hindi available',
        'This item has no trade requirements configured yet.': 'Wala pang naka-set na trade requirements para sa item na ’to.',
        'Trade request submitted': 'Na-submit na yung trade request mo',
        'Reference:': 'Reference:',
        'Status:': 'Status:',
        'Close this window to see the updated request in your Trade History.': 'Isara itong window para makita yung updated request sa Trade History mo.',
        'Requirements are no longer configured for this trade item.': 'Wala nang naka-set na requirements para sa trade item na ’to.',
        'AVAILABLE TRADE ITEMS': 'AVAILABLE NA TRADE ITEMS',
        'TRADE HISTORY': 'TRADE HISTORY',
        'Offer': 'Offer',
        'Payment': 'Payment',
        'PAYMENT': 'PAYMENT',
        'Date': 'Date',
        'Actions': 'Actions',
        'No matching results': 'Walang results',
        'Try adjusting your search or filters.': 'Baguhin yung search o filters mo at subukan ulit.',
        'Offers.': 'Offers.',
        'Review VNZ TECH offers and follow every counter-offer in one place.': 'Dito mo makikita yung offers at counter-offers mula sa VNZ TECH.',
        'NEEDS YOUR RESPONSE': 'KAILANGAN NG REPLY MO',
        'WAITING FOR VNZ TECH': 'WAITING SA VNZ TECH',
        'CLOSED': 'CLOSED',
        'TOTAL OFFERS': 'TOTAL OFFERS',
        'Search offers': 'Search offers',
        'Filter offers': 'Filter offers',
        'Something went wrong while loading your offers. Please try again later.': 'May error habang nilo-load yung offers mo. Subukan ulit mamaya.',
        'Something went wrong while loading your transactions.': 'May error habang nilo-load yung transactions mo.',
        'Something went wrong while loading your profile.': 'May error habang nilo-load yung profile mo.',
        'Close transaction details': 'Isara ang transaction details',
        'Loading transaction details...': 'Nilo-load yung transaction details...',
        'REFERENCE': 'REFERENCE',
        'SUBMITTED': 'SUBMITTED',
        'Qty': 'Qty',
        'Wholesale': 'Wholesale',
        'Per Piece': 'Per piece',
        'CURRENT OFFER': 'CURRENT OFFER',
        'TRANSACTION': 'TRANSACTION',
        'CREATED': 'CREATED',
        'SUBMISSION': 'SUBMISSION',
        'FINAL AMOUNT': 'FINAL AMOUNT',
        'Direct Admin Acceptance': 'Tinanggap ng admin',
        'Transaction created': 'Nagawa na yung transaction',
        'Please log in again or contact VNZ TECH.': 'Mag-log in ulit o i-contact ang VNZ TECH.',
        'Close trade items': 'Isara yung trade items',
    };

    // Product copy is kept separately so wording can evolve without changing
    // traversal rules. Only known UI strings are translated, never substrings
    // from arbitrary names, references, notes, messages, or form values.
    Object.entries(window.VNZSellerLanguageCopy || {}).forEach(([source, target]) => {
        if (typeof target === 'string') {
            translations[source] = target;
        }
    });

    const protectedContent = [
        'script', 'style', 'noscript', 'textarea', 'code', 'pre', 'svg', 'math',
        '[contenteditable]:not([contenteditable="false"])', '[translate="no"]',
        '[data-user-content]', '[data-vnz-i18n-ignore]',
        '.chat-message', '.chat-message-content', '.message-content',
        '.trade-history-description', '.trade-history-admin-notes p',
        '.trade-request-option-description', '.trade-history-requirements small',
        '.settings-email-address', '.seller-profile-card h2', '.seller-profile-card p',
        '.seller-profile-avatar', '.profile-info strong', '.profile-info span',
        '.profile-strip-left > div:last-child', '.profile-avatar',
        '.account-info-card strong', '.recent-reference', '.seller-offer-reference',
        '.seller-offer-card h3', '.seller-offer-message p', '.reference-block strong',
        '.submission-item-summary h2', '.trade-history-reference',
        '.trade-history-header h2', '.trade-request-option-heading h3',
        '.trade-request-option-heading span', '.board-code'
    ].join(', ');
    const attributes = ['placeholder', 'aria-label', 'title'];
    const translatedNodes = new WeakMap();
    const translatedAttributes = new WeakMap();

    function normalize(value) {
        return value.replace(/\s+/g, ' ').trim();
    }

    function lookup(value) {
        return Object.prototype.hasOwnProperty.call(translations, value)
            && typeof translations[value] === 'string'
            ? translations[value]
            : null;
    }

    function translatePhrase(value) {
        const direct = lookup(value);
        if (direct !== null) {
            return direct;
        }

        let match = value.match(/^([\d,]+) offers? in this negotiation$/);
        if (match) {
            return `${match[1]} offer${match[1] === '1' ? '' : 's'} sa usapang 'to`;
        }
        match = value.match(/^(All|Needs your response|Waiting for VNZ TECH|Closed|Under review) \(([\d,]+)\)$/);
        if (match) {
            return `${lookup(match[1]) || match[1]} (${match[2]})`;
        }
        match = value.match(/^Showing ([\d,]+)\s*([\u2013\u2014-])\s*([\d,]+) of ([\d,]+)$/);
        if (match) {
            return `Pinapakita ang ${match[1]}${match[2]}${match[3]} sa ${match[4]}`;
        }
        match = value.match(/^Page ([\d,]+) of ([\d,]+)$/);
        if (match) {
            return `Page ${match[1]} sa ${match[2]}`;
        }
        match = value.match(/^([\d,]+) total trade requests$/);
        if (match) {
            return `${match[1]} trade requests lahat`;
        }
        match = value.match(/^(Submission|Transaction|Approved amount|Reference|Status):\s*(.+)$/);
        if (match) {
            const labels = {
                Submission: 'Submission', Transaction: 'Transaction',
                'Approved amount': 'Approved na amount', Reference: 'Reference', Status: 'Status'
            };
            return `${lookup(match[1] + ':') || labels[match[1]] + ':'} ${match[2]}`;
        }
        match = value.match(/^Are you sure you want to (accept|decline) the offer of (\u20b1[\d,.]+|PHP [\d,.]+|this offer) for submission (#?SP-[A-Za-z0-9-]+)\?$/);
        if (match) {
            return `${match[1] === 'accept' ? 'Tanggapin' : 'I-decline'} mo na ba yung offer na ${match[2] === 'this offer' ? "'to" : match[2]} para sa submission ${match[3]}?`;
        }
        match = value.match(/^Send your counter-offer of (\u20b1[\d,.]+|PHP [\d,.]+) for submission (#?SP-[A-Za-z0-9-]+)\?$/);
        if (match) {
            return `I-send na yung counter-offer mong ${match[1]} para sa submission ${match[2]}?`;
        }
        return value;
    }

    function translate(value) {
        if (!isFilipino || typeof value !== 'string' || value.trim() === '') {
            return value;
        }
        const normalized = normalize(value);
        let translated = translatePhrase(normalized);
        if (translated === normalized && /[\r\n]/.test(value)) {
            // Alerts contain separate detail lines. Keep their newlines and
            // interpolate references/amounts verbatim instead of flattening them.
            return value.split(/(\r?\n)/).map(part => {
                if (/^\r?\n$/.test(part)) return part;
                const phrase = normalize(part);
                const result = translatePhrase(phrase);
                return result === phrase ? part : preserveSpacing(part, result);
            }).join('');
        }
        return translated === normalized ? value : preserveSpacing(value, translated);
    }

    function preserveSpacing(source, translated) {
        const leading = source.match(/^\s*/)[0];
        const trailing = source.match(/\s*$/)[0];
        return leading + translated + trailing;
    }

    function isProtected(element) {
        return !element || Boolean(element.closest(protectedContent));
    }

    function translateNode(node) {
        if (isProtected(node.parentElement)) return;
        const current = node.nodeValue;
        if (translatedNodes.get(node) === current) return;
        const translated = translate(current);
        if (translated !== current) {
            translatedNodes.set(node, translated);
            node.nodeValue = translated;
        }
    }

    function translateAttributes(element) {
        if (isProtected(element)) return;
        let previous = translatedAttributes.get(element);
        if (!previous) {
            previous = new Map();
            translatedAttributes.set(element, previous);
        }
        attributes.forEach(attribute => {
            const current = element.getAttribute(attribute);
            if (current === null || previous.get(attribute) === current) return;
            const translated = translate(current);
            if (translated !== current) {
                previous.set(attribute, translated);
                element.setAttribute(attribute, translated);
            }
        });
    }

    function translateElement(root) {
        if (!isFilipino || !root) return;
        if (root.nodeType === Node.TEXT_NODE) {
            translateNode(root);
            return;
        }
        // Walk individual nodes: icons, nested counts, controls, and listeners
        // survive translation. Never replace an element's innerHTML/textContent.
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        let node;
        while ((node = walker.nextNode())) translateNode(node);
        if (root.nodeType === Node.ELEMENT_NODE) translateAttributes(root);
        if (root.querySelectorAll) {
            root.querySelectorAll('[placeholder], [aria-label], [title]').forEach(translateAttributes);
        }
    }

    window.VNZSellerLanguage = Object.freeze({translate, translateElement});

    function start() {
        if (!isFilipino || !document.body) return;
        translateElement(document.body);
        const observer = new MutationObserver(records => {
            const addedRoots = new Set();
            records.forEach(record => {
                if (record.type === 'characterData') translateNode(record.target);
                if (record.type === 'attributes') translateAttributes(record.target);
                record.addedNodes.forEach(node => {
                    if (node.nodeType === Node.TEXT_NODE) translateNode(node);
                    if (node.nodeType === Node.ELEMENT_NODE) addedRoots.add(node);
                });
            });
            addedRoots.forEach(root => {
                if (root.isConnected) translateElement(root);
            });
        });
        observer.observe(document.body, {
            childList: true, characterData: true, attributes: true,
            attributeFilter: attributes, subtree: true
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();

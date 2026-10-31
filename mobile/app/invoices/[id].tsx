import { useLocalSearchParams } from "expo-router";
import { useEffect, useState } from "react";
import { ActivityIndicator, StyleSheet, Text, View } from "react-native";
import { fetchInvoice } from "../../lib/api";
import { formatMoney, statusLabel } from "../../lib/format";
import type { Invoice } from "../../lib/types";

export default function InvoiceScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const [invoice, setInvoice] = useState<Invoice | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    fetchInvoice(Number(id)).then(setInvoice, () => setFailed(true));
  }, [id]);

  if (failed) {
    return (
      <View style={styles.screen}>
        <Text>Couldn&apos;t load this invoice.</Text>
      </View>
    );
  }

  if (!invoice) {
    return (
      <View style={styles.screen}>
        <ActivityIndicator accessibilityLabel="Loading" />
      </View>
    );
  }

  return (
    <View style={styles.screen}>
      <Text style={styles.number}>{invoice.number}</Text>
      <Text style={styles.amount}>{formatMoney(invoice.amount_cents, invoice.currency)}</Text>
      <Text style={[styles.status, invoice.overdue && styles.overdue]}>{statusLabel(invoice)}</Text>
      <Text style={styles.line}>{invoice.description}</Text>
      <Text style={styles.line}>{invoice.customer.name}</Text>
      <Text style={styles.muted}>Due {invoice.due_on}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, padding: 24, backgroundColor: "#fff" },
  number: { fontSize: 14, color: "#52525b" },
  amount: { marginTop: 8, fontSize: 32, fontWeight: "600", fontVariant: ["tabular-nums"] },
  status: { marginTop: 4, fontSize: 16 },
  overdue: { color: "#b91c1c" },
  line: { marginTop: 16, fontSize: 16 },
  muted: { marginTop: 4, color: "#52525b" },
});
